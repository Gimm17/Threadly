<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\PostMedia;
use App\Models\Post;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

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
