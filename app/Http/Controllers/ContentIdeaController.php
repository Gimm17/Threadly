<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\StoreContentIdeaRequest;
use App\Models\ContentIdea;
use App\Models\Post;
use Illuminate\Http\RedirectResponse;

class ContentIdeaController extends Controller
{
    public function store(StoreContentIdeaRequest $request): RedirectResponse
    {
        ContentIdea::create([
            ...$request->validated(),
            'created_by' => auth()->id(),
        ]);

        return redirect()->route('content-planner')
            ->with('success', 'Ide konten berhasil dibuat.');
    }

    public function update(StoreContentIdeaRequest $request, ContentIdea $contentIdea): RedirectResponse
    {
        $contentIdea->update($request->validated());

        return redirect()->route('content-planner')
            ->with('success', 'Ide konten berhasil diperbarui.');
    }

    public function destroy(ContentIdea $contentIdea): RedirectResponse
    {
        $contentIdea->delete();

        return redirect()->route('content-planner')
            ->with('success', 'Ide konten berhasil dihapus.');
    }

    /**
     * Convert a content idea into a draft post.
     */
    public function convertToPost(ContentIdea $contentIdea): RedirectResponse
    {
        $post = Post::create([
            'workspace_id' => $contentIdea->workspace_id,
            'content_idea_id' => $contentIdea->id,
            'content_pillar_id' => $contentIdea->content_pillar_id,
            'hook' => $contentIdea->title,
            'body' => $contentIdea->notes ?? $contentIdea->title,
            'status' => 'draft',
            'scheduled_at' => $contentIdea->target_date,
            'publish_mode' => 'manual',
            'created_by' => auth()->id(),
        ]);

        $contentIdea->update(['status' => 'in_progress']);

        return redirect()->route('posts.edit', $post)
            ->with('success', 'Ide konten berhasil dikonversi menjadi draft post.');
    }
}
