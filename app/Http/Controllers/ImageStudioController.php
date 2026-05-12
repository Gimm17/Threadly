<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\GeneratedMedia;
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
     * Generate an AI image via Text-to-Image (/v1/images/generations).
     */
    public function generate(Request $request): JsonResponse
    {
        set_time_limit(180);

        $validated = $request->validate([
            'prompt' => ['required', 'string', 'max:1000'],
            'style' => ['nullable', 'string', 'in:realistic,illustration,cartoon,minimalist,3d,photography'],
            'aspect_ratio' => ['nullable', 'string', 'in:1:1,4:5,16:9,9:16'],
            'quality' => ['nullable', 'string', 'in:auto,low,medium,high'],
            'background' => ['nullable', 'string', 'in:auto,transparent,opaque'],
        ]);

        try {
            $fileInfo = $this->aiService->generateImage(
                prompt: $validated['prompt'],
                style: $validated['style'] ?? 'realistic',
                aspectRatio: $validated['aspect_ratio'] ?? '1:1',
                quality: $validated['quality'] ?? 'auto',
                background: $validated['background'] ?? 'auto',
            );

            $media = $this->saveMedia($validated, $fileInfo, 'text-to-image');

            return response()->json([
                'success' => true,
                'media' => $this->mediaResponse($media),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal generate gambar: ' . $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Edit an image via /v1/images/edits (upload + prompt).
     */
    public function edit(Request $request): JsonResponse
    {
        set_time_limit(180);

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
                'message' => 'Gagal edit gambar: ' . $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Generate image via Multimodal Chat Completions.
     */
    public function generateFromChat(Request $request): JsonResponse
    {
        set_time_limit(180);

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
                'message' => 'Gagal generate gambar via chat: ' . $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Generate image from reference image via Multimodal Chat Completions.
     */
    public function generateFromReference(Request $request): JsonResponse
    {
        set_time_limit(180);

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
                'message' => 'Gagal generate dari referensi: ' . $e->getMessage(),
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
        Storage::disk('public')->delete($media->file_path);
        $media->delete();

        return back()->with('success', 'Gambar berhasil dihapus.');
    }

    /**
     * Save generated media to database.
     */
    private function saveMedia(array $validated, array $fileInfo, string $generationMode): GeneratedMedia
    {
        $imageConfig = \App\Models\AiModelConfig::withoutGlobalScopes()
            ->where('workspace_id', auth()->user()->workspace_id)
            ->where('feature', 'image_generation')
            ->first();

        $modelUsed = $imageConfig?->model_id ?? 'gpt-image-1';

        return GeneratedMedia::create([
            'workspace_id' => auth()->user()->workspace_id,
            'created_by' => auth()->id(),
            'type' => 'image',
            'file_path' => $fileInfo['path'],
            'file_name' => $fileInfo['name'],
            'mime_type' => $fileInfo['mime'],
            'file_size' => $fileInfo['size'],
            'prompt' => $validated['prompt'],
            'provider' => 'tokenrouter',
            'model_id' => $modelUsed,
            'style' => $validated['style'] ?? null,
            'aspect_ratio' => $validated['aspect_ratio'] ?? '1:1',
            'generation_mode' => $generationMode,
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
            'prompt' => $media->prompt,
            'style' => $media->style,
            'aspect_ratio' => $media->aspect_ratio,
            'generation_mode' => $media->generation_mode ?? 'text-to-image',
            'created_at' => $media->created_at->diffForHumans(),
        ];
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
