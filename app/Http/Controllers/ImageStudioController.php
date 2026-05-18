<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Jobs\GeneratePosterImageJob;
use App\Models\GeneratedMedia;
use App\Services\AI\CostEstimator;
use App\Services\AI\ModelCatalogService;
use App\Services\AI\PosterPromptBuilder;
use App\Services\AIService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

class ImageStudioController extends Controller
{
    public function __construct(
        private readonly AIService $aiService,
        private readonly PosterPromptBuilder $posterPromptBuilder,
        private readonly CostEstimator $costEstimator,
        private readonly ModelCatalogService $modelCatalog,
    ) {}

    /**
     * Display the Image Studio page with gallery.
     */
    public function index(Request $request): Response
    {
        $media = GeneratedMedia::where('type', 'image')
            ->orderByDesc('created_at')
            ->paginate(12);

        return Inertia::render('ImageStudio/Index', [
            'media' => $media,
            'styles' => self::availableStyles(),
            'aspectRatios' => self::availableAspectRatios(),
            'qualities' => self::availableQualities(),
            'backgrounds' => self::availableBackgrounds(),
        ]);
    }

    /**
     * Generate a poster-optimized image with deterministic overlay metadata.
     */
    public function poster(Request $request): JsonResponse
    {
        $this->extendImageExecutionTime();

        $validated = $request->validate([
            'prompt' => ['required', 'string', 'max:1200'],
            'headline' => ['nullable', 'string', 'max:80'],
            'style' => ['nullable', 'string', 'in:realistic,illustration,cartoon,minimalist,3d,photography'],
            'aspect_ratio' => ['nullable', 'string', 'in:1:1,4:5,16:9,9:16'],
            'quality' => ['nullable', 'string', 'in:auto,low,medium,high'],
            'background' => ['nullable', 'string', 'in:auto,transparent,opaque'],
            'allow_ai_text' => ['nullable', 'boolean'],
        ]);

        $imageConfig = \App\Models\AiModelConfig::withoutGlobalScopes()
            ->where('workspace_id', auth()->user()->workspace_id)
            ->where('feature', 'image_generation')
            ->first();
        $modelUsed = $imageConfig?->model_id ?? 'google/gemini-3.1-flash-image-preview';

        if (($validated['allow_ai_text'] ?? false) && ! $this->modelCatalog->supportsTextInImage($modelUsed)) {
            $validated['allow_ai_text'] = false;
        }

        $promptData = $this->posterPromptBuilder->build($validated, auth()->user()->workspace_id);
        $metadata = [
            ...$promptData,
            'estimated_cost' => $this->costEstimator->imageCost($modelUsed),
        ];

        try {
            if (filter_var(env('AI_IMAGE_QUEUE_ENABLED', false), FILTER_VALIDATE_BOOL)) {
                $media = $this->createQueuedMedia($validated, $metadata, $modelUsed);
                GeneratePosterImageJob::dispatch($media->id, $validated)->onQueue('ai');

                return response()->json([
                    'success' => true,
                    'queued' => true,
                    'media' => $this->mediaResponse($media),
                ]);
            }

            $fileInfo = $this->aiService->generateImage(
                prompt: $promptData['enhanced_prompt'],
                style: $validated['style'] ?? 'realistic',
                aspectRatio: $validated['aspect_ratio'] ?? '1:1',
                quality: $validated['quality'] ?? 'auto',
                background: $validated['background'] ?? 'auto',
            );

            $media = $this->saveMedia($validated, $fileInfo, 'poster', $metadata);

            return response()->json([
                'success' => true,
                'media' => $this->mediaResponse($media),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal generate poster.',
            ], 422);
        }
    }

    public function jobStatus(GeneratedMedia $media): JsonResponse
    {
        abort_unless($media->workspace_id === auth()->user()->workspace_id, 404);

        return response()->json([
            'success' => true,
            'media' => $this->mediaResponse($media->fresh()),
        ]);
    }

    /**
     * Generate an AI image via Text-to-Image (/v1/images/generations).
     */
    public function generate(Request $request): JsonResponse
    {
        $this->extendImageExecutionTime();

        $validated = $request->validate([
            'prompt' => ['required', 'string', 'max:1000'],
            'style' => ['nullable', 'string', 'in:realistic,illustration,cartoon,minimalist,3d,photography'],
            'aspect_ratio' => ['nullable', 'string', 'in:1:1,4:5,16:9,9:16'],
            'quality' => ['nullable', 'string', 'in:auto,low,medium,high'],
            'background' => ['nullable', 'string', 'in:auto,transparent,opaque'],
        ]);

        try {
            $promptData = $this->posterPromptBuilder->build($validated, auth()->user()->workspace_id);
            $imageConfig = \App\Models\AiModelConfig::withoutGlobalScopes()
                ->where('workspace_id', auth()->user()->workspace_id)
                ->where('feature', 'image_generation')
                ->first();
            $modelUsed = $imageConfig?->model_id ?? 'google/gemini-3.1-flash-image-preview';

            $fileInfo = $this->aiService->generateImage(
                prompt: $promptData['enhanced_prompt'],
                style: $validated['style'] ?? 'realistic',
                aspectRatio: $validated['aspect_ratio'] ?? '1:1',
                quality: $validated['quality'] ?? 'auto',
                background: $validated['background'] ?? 'auto',
            );

            $media = $this->saveMedia($validated, $fileInfo, 'advanced', [
                ...$promptData,
                'estimated_cost' => $this->costEstimator->imageCost($modelUsed),
            ]);

            return response()->json([
                'success' => true,
                'media' => $this->mediaResponse($media),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal generate gambar.',
            ], 422);
        }
    }

    /**
     * Edit an image via /v1/images/edits (upload + prompt).
     */
    public function edit(Request $request): JsonResponse
    {
        $this->extendImageExecutionTime();

        $validated = $request->validate([
            'prompt' => ['required', 'string', 'max:1000'],
            'image' => ['required', 'file', 'image', 'max:10240'], // max 10MB
            'aspect_ratio' => ['nullable', 'string', 'in:1:1,4:5,16:9,9:16'],
        ]);

        try {
            // Store uploaded image temporarily
            $uploadedPath = $request->file('image')->store(
                'workspaces/' . auth()->user()->workspace_id . '/temp',
                'public'
            );

            $size = match ($validated['aspect_ratio'] ?? '1:1') {
                '1:1' => '1024x1024',
                '16:9' => '1536x1024',
                '9:16' => '1024x1536',
                '4:5' => '1024x1280',
                default => '1024x1024',
            };

            $fileInfo = $this->aiService->editImage(
                prompt: $validated['prompt'],
                imagePaths: $uploadedPath,
                size: $size,
            );

            // Clean up temp file
            Storage::disk('public')->delete($uploadedPath);

            $media = $this->saveMedia($validated, $fileInfo, 'image-edit');

            return response()->json([
                'success' => true,
                'media' => $this->mediaResponse($media),
            ]);
        } catch (\Exception $e) {
            // Clean up temp file on error
            if (isset($uploadedPath)) {
                Storage::disk('public')->delete($uploadedPath);
            }

            return response()->json([
                'success' => false,
                'message' => 'Gagal edit gambar.',
            ], 422);
        }
    }

    /**
     * Generate image via Multimodal Chat Completions.
     */
    public function generateFromChat(Request $request): JsonResponse
    {
        $this->extendImageExecutionTime();

        $validated = $request->validate([
            'prompt' => ['required', 'string', 'max:1000'],
            'style' => ['nullable', 'string', 'in:realistic,illustration,cartoon,minimalist,3d,photography'],
            'aspect_ratio' => ['nullable', 'string', 'in:1:1,4:5,16:9,9:16'],
        ]);

        try {
            $fileInfo = $this->aiService->generateImageViaChat(
                prompt: $validated['prompt'],
                style: $validated['style'] ?? 'realistic',
                aspectRatio: $validated['aspect_ratio'] ?? '1:1',
            );

            $media = $this->saveMedia($validated, $fileInfo, 'chat-generation');

            return response()->json([
                'success' => true,
                'media' => $this->mediaResponse($media),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal generate gambar via chat.',
            ], 422);
        }
    }

    /**
     * Generate image from reference image via Multimodal Chat Completions.
     */
    public function generateFromReference(Request $request): JsonResponse
    {
        $this->extendImageExecutionTime();

        $validated = $request->validate([
            'prompt' => ['required', 'string', 'max:1000'],
            'reference_image' => ['required_without:reference_url', 'nullable', 'file', 'image', 'max:10240'],
            'reference_url' => ['required_without:reference_image', 'nullable', 'url', 'max:2048'],
            'aspect_ratio' => ['nullable', 'string', 'in:1:1,4:5,16:9,9:16'],
        ]);

        try {
            $referenceUrl = $validated['reference_url'] ?? null;
            $uploadedPath = null;

            // If file uploaded, store and create a public URL
            if ($request->hasFile('reference_image')) {
                $uploadedPath = $request->file('reference_image')->store(
                    'workspaces/' . auth()->user()->workspace_id . '/temp',
                    'public'
                );
                $referenceUrl = url(Storage::url($uploadedPath));
            }

            if (!$referenceUrl) {
                throw new \RuntimeException('Reference image URL or file is required.');
            }

            $fileInfo = $this->aiService->generateImageFromReference(
                prompt: $validated['prompt'],
                referenceImageUrl: $referenceUrl,
                aspectRatio: $validated['aspect_ratio'] ?? '1:1',
            );

            // Clean up temp file
            if ($uploadedPath) {
                Storage::disk('public')->delete($uploadedPath);
            }

            $media = $this->saveMedia($validated, $fileInfo, 'reference-generation');

            return response()->json([
                'success' => true,
                'media' => $this->mediaResponse($media),
            ]);
        } catch (\Exception $e) {
            if (isset($uploadedPath)) {
                Storage::disk('public')->delete($uploadedPath);
            }

            return response()->json([
                'success' => false,
                'message' => 'Gagal generate dari referensi.',
            ], 422);
        }
    }

    /**
     * Toggle favorite on a generated media.
     */
    public function toggleFavorite(GeneratedMedia $media): JsonResponse
    {
        $media->update(['is_favorite' => !$media->is_favorite]);

        return response()->json([
            'success' => true,
            'is_favorite' => $media->is_favorite,
        ]);
    }

    /**
     * Delete a generated media.
     */
    public function destroy(GeneratedMedia $media): RedirectResponse
    {
        if (filled($media->file_path)) {
            Storage::disk('public')->delete($media->file_path);
        }

        $media->delete();

        return back()->with('success', 'Gambar berhasil dihapus.');
    }

    /**
     * Save generated media to database.
     */
    private function saveMedia(array $validated, array $fileInfo, string $generationMode, array $metadata = []): GeneratedMedia
    {
        $imageConfig = \App\Models\AiModelConfig::withoutGlobalScopes()
            ->where('workspace_id', auth()->user()->workspace_id)
            ->where('feature', 'image_generation')
            ->first();

        $modelUsed = $imageConfig?->model_id ?? 'google/gemini-3.1-flash-image-preview';

        return GeneratedMedia::create([
            'workspace_id' => auth()->user()->workspace_id,
            'created_by' => auth()->id(),
            'type' => 'image',
            'file_path' => $fileInfo['path'],
            'file_name' => $fileInfo['name'],
            'mime_type' => $fileInfo['mime'],
            'file_size' => $fileInfo['size'],
            'prompt' => $validated['prompt'],
            'original_prompt' => $metadata['original_prompt'] ?? $validated['prompt'],
            'enhanced_prompt' => $metadata['enhanced_prompt'] ?? null,
            'negative_prompt' => $metadata['negative_prompt'] ?? null,
            'provider' => 'tokenrouter',
            'model_id' => $modelUsed,
            'style' => $validated['style'] ?? null,
            'aspect_ratio' => $validated['aspect_ratio'] ?? '1:1',
            'generation_mode' => $generationMode,
            'model_params' => $metadata['model_params'] ?? null,
            'overlay_config' => $metadata['overlay_config'] ?? null,
            'prompt_template_version' => $metadata['prompt_template_version'] ?? null,
            'generation_status' => $metadata['generation_status'] ?? 'completed',
            'estimated_cost' => $metadata['estimated_cost'] ?? $this->costEstimator->imageCost($modelUsed),
        ]);
    }

    private function createQueuedMedia(array $validated, array $metadata, string $modelUsed): GeneratedMedia
    {
        return GeneratedMedia::create([
            'workspace_id' => auth()->user()->workspace_id,
            'created_by' => auth()->id(),
            'type' => 'image',
            'file_path' => '',
            'file_name' => '',
            'mime_type' => 'image/png',
            'file_size' => 0,
            'prompt' => $validated['prompt'],
            'original_prompt' => $metadata['original_prompt'] ?? $validated['prompt'],
            'enhanced_prompt' => $metadata['enhanced_prompt'] ?? null,
            'negative_prompt' => $metadata['negative_prompt'] ?? null,
            'provider' => 'tokenrouter',
            'model_id' => $modelUsed,
            'style' => $validated['style'] ?? null,
            'aspect_ratio' => $validated['aspect_ratio'] ?? '1:1',
            'generation_mode' => 'poster',
            'model_params' => $metadata['model_params'] ?? null,
            'overlay_config' => $metadata['overlay_config'] ?? null,
            'prompt_template_version' => $metadata['prompt_template_version'] ?? null,
            'generation_status' => 'queued',
            'estimated_cost' => $metadata['estimated_cost'] ?? null,
        ]);
    }

    /**
     * Format media for JSON response.
     */
    private function mediaResponse(GeneratedMedia $media): array
    {
        return [
            'id' => $media->id,
            'url' => $media->url,
            'file_name' => $media->file_name,
            'prompt' => $media->prompt,
            'style' => $media->style,
            'aspect_ratio' => $media->aspect_ratio,
            'generation_mode' => $media->generation_mode ?? 'text-to-image',
            'generation_status' => $media->generation_status ?? 'completed',
            'enhanced_prompt' => $media->enhanced_prompt,
            'overlay_config' => $media->overlay_config,
            'estimated_cost' => $media->estimated_cost,
            'error_message' => $media->error_message,
            'created_at' => $media->created_at->diffForHumans(),
        ];
    }

    private function extendImageExecutionTime(): void
    {
        $seconds = max(120, min(930, (int) config('services.tokenrouter.image_timeout', 180) + 30));

        if (function_exists('ini_set')) {
            @ini_set('max_execution_time', (string) $seconds);
        }

        if (function_exists('set_time_limit')) {
            @set_time_limit($seconds);
        }
    }

    public static function availableStyles(): array
    {
        return [
            ['value' => 'realistic', 'label' => 'Realistic'],
            ['value' => 'illustration', 'label' => 'Illustration'],
            ['value' => 'cartoon', 'label' => 'Cartoon'],
            ['value' => 'minimalist', 'label' => 'Minimalist'],
            ['value' => '3d', 'label' => '3D Render'],
            ['value' => 'photography', 'label' => 'Photography'],
        ];
    }

    public static function availableAspectRatios(): array
    {
        return [
            ['value' => '1:1', 'label' => '1:1 (Square)'],
            ['value' => '4:5', 'label' => '4:5 (Portrait)'],
            ['value' => '16:9', 'label' => '16:9 (Landscape)'],
            ['value' => '9:16', 'label' => '9:16 (Story)'],
        ];
    }

    public static function availableQualities(): array
    {
        return [
            ['value' => 'auto', 'label' => 'Auto'],
            ['value' => 'low', 'label' => 'Low'],
            ['value' => 'medium', 'label' => 'Medium'],
            ['value' => 'high', 'label' => 'High'],
        ];
    }

    public static function availableBackgrounds(): array
    {
        return [
            ['value' => 'auto', 'label' => 'Auto'],
            ['value' => 'transparent', 'label' => 'Transparent'],
            ['value' => 'opaque', 'label' => 'Opaque'],
        ];
    }
}
