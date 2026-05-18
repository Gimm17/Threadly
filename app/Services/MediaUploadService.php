<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\GeneratedMedia;
use App\Models\PostMedia;
use App\Models\Post;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class MediaUploadService
{
    public function upload(UploadedFile $file, Post $post): PostMedia
    {
        $path = "workspaces/{$post->workspace_id}/posts/{$post->id}";
        $storedPath = $file->store($path, 'public');
        $type = $this->detectType($file->getMimeType());

        return PostMedia::create([
            'post_id' => $post->id,
            'file_path' => $storedPath,
            'file_name' => $file->getClientOriginalName(),
            'mime_type' => $file->getMimeType(),
            'file_size' => $file->getSize(),
            'type' => $type,
            'sort_order' => $post->media()->count(),
        ]);
    }

    public function attachGeneratedMediaIds(array $ids, Post $post): void
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $ids))));

        if ($ids === []) {
            return;
        }

        GeneratedMedia::withoutGlobalScopes()
            ->where('workspace_id', $post->workspace_id)
            ->whereIn('id', $ids)
            ->where('generation_status', 'completed')
            ->get()
            ->each(fn (GeneratedMedia $media) => $this->attachGeneratedMedia($media, $post));
    }

    public function attachGeneratedMedia(GeneratedMedia $media, Post $post): PostMedia
    {
        if ($media->workspace_id !== $post->workspace_id) {
            throw new \RuntimeException('Generated media does not belong to this workspace.');
        }

        if (blank($media->file_path) || ! Storage::disk('public')->exists($media->file_path)) {
            throw new \RuntimeException('Generated media file is not available.');
        }

        $targetPath = $this->generatedMediaTargetPath($media, $post);
        Storage::disk('public')->put($targetPath, Storage::disk('public')->get($media->file_path));

        $postMedia = PostMedia::create([
            'post_id' => $post->id,
            'file_path' => $targetPath,
            'file_name' => $media->file_name,
            'mime_type' => $media->mime_type,
            'file_size' => Storage::disk('public')->size($targetPath),
            'type' => $this->detectType($media->mime_type),
            'sort_order' => $post->media()->count(),
            'is_ai_generated' => true,
            'ai_prompt' => mb_substr((string) ($media->enhanced_prompt ?? $media->prompt), 0, 255),
        ]);

        $media->update(['post_id' => $post->id]);

        return $postMedia;
    }

    private function generatedMediaTargetPath(GeneratedMedia $media, Post $post): string
    {
        $extension = pathinfo($media->file_name, PATHINFO_EXTENSION)
            ?: match ($media->mime_type) {
                'image/jpeg', 'image/jpg' => 'jpg',
                'image/webp' => 'webp',
                'image/gif' => 'gif',
                default => 'png',
            };
        $baseName = pathinfo($media->file_name, PATHINFO_FILENAME) ?: 'ai-image';
        $safeName = Str::slug($baseName) ?: 'ai-image';

        return "workspaces/{$post->workspace_id}/posts/{$post->id}/ai-{$media->id}-{$safeName}.{$extension}";
    }

    private function detectType(string $mimeType): string
    {
        if (str_starts_with($mimeType, 'video/')) {
            return 'video';
        }

        if ($mimeType === 'image/gif') {
            return 'gif';
        }

        return 'image';
    }
}
