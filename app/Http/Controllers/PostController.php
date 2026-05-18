<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\StorePostRequest;
use App\Jobs\PublishToThreadsJob;
use App\Models\ActivityLog;
use App\Models\ContentPillar;
use App\Models\Post;
use App\Services\MediaUploadService;
use App\Services\PostReminderService;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class PostController extends Controller
{
    public function __construct(
        private readonly MediaUploadService $mediaService,
        private readonly PostReminderService $postReminderService,
    ) {}

    public function index(): Response
    {
        $status = request('status');

        return Inertia::render('Posts/Index', [
            'posts' => Post::with(['contentPillar', 'media'])
                ->when($status, fn ($q) => $q->forStatus($status))
                ->orderByDesc('created_at')
                ->paginate(15)
                ->through(fn ($post) => [
                    'id' => $post->id,
                    'body' => $post->body,
                    'hook' => $post->hook,
                    'status' => $post->status,
                    'status_label' => $post->status_label,
                    'status_color' => $post->status_color,
                    'scheduled_at' => $post->scheduled_at?->format('Y-m-d H:i'),
                    'scheduled_display' => $post->scheduled_at?->translatedFormat('d M Y, H:i') . ' WIB',
                    'published_at' => $post->published_at?->format('Y-m-d H:i'),
                    'pillar' => $post->contentPillar?->name,
                    'pillar_color' => $post->contentPillar?->color_hex,
                    'media' => $post->media->map(fn ($m) => [
                        'id' => $m->id,
                        'url' => $m->url,
                        'type' => $m->type,
                    ]),
                    'created_at' => $post->created_at->diffForHumans(),
                ]),
            'filters' => [
                'status' => $status,
            ],
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Posts/Create', [
            'pillars' => ContentPillar::active()->ordered()->get(['id', 'name', 'color_hex']),
        ]);
    }

    public function show(Post $post): RedirectResponse
    {
        return redirect()->route('posts.edit', $post);
    }

    public function store(StorePostRequest $request): RedirectResponse
    {
        $post = Post::create([
            ...$request->safe()->except('media'),
            'created_by' => auth()->id(),
        ]);

        if ($request->hasFile('media')) {
            foreach ($request->file('media') as $file) {
                $this->mediaService->upload($file, $post);
            }
        }

        $this->postReminderService->scheduleReminder($post);
        $this->logActivity('created', $post);

        return redirect()->route('posts.index')
            ->with('success', 'Post berhasil dibuat.');
    }

    public function edit(Post $post): Response
    {
        return Inertia::render('Posts/Edit', [
            'post' => [
                'id' => $post->id,
                'body' => $post->body,
                'hook' => $post->hook,
                'status' => $post->status,
                'content_pillar_id' => $post->content_pillar_id,
                'scheduled_at' => $post->scheduled_at?->format('Y-m-d\TH:i'),
                'publish_mode' => $post->publish_mode,
                'link_url' => $post->link_url,
                'notes' => $post->notes,
                'media' => $post->media->map(fn ($m) => [
                    'id' => $m->id,
                    'url' => $m->url,
                    'type' => $m->type,
                    'file_name' => $m->file_name,
                ]),
            ],
            'pillars' => ContentPillar::active()->ordered()->get(['id', 'name', 'color_hex']),
        ]);
    }

    public function update(StorePostRequest $request, Post $post): RedirectResponse
    {
        $post->update($request->safe()->except('media'));

        if ($request->hasFile('media')) {
            foreach ($request->file('media') as $file) {
                $this->mediaService->upload($file, $post);
            }
        }

        $this->postReminderService->scheduleReminder($post);
        $this->logActivity('updated', $post);

        return redirect()->route('posts.index')
            ->with('success', 'Post berhasil diperbarui.');
    }

    public function destroy(Post $post): RedirectResponse
    {
        $this->logActivity('deleted', $post, [
            'body' => mb_substr($post->body, 0, 100),
        ]);

        $post->delete();

        return redirect()->route('posts.index')
            ->with('success', 'Post berhasil dihapus.');
    }

    public function markPublished(Post $post): RedirectResponse
    {
        $post->update([
            'status' => 'published',
            'published_at' => now(),
        ]);

        $this->logActivity('published', $post);

        return redirect()->back()
            ->with('success', 'Post ditandai sebagai Published.');
    }

    public function cancel(Post $post): RedirectResponse
    {
        $post->update(['status' => 'cancelled']);

        $this->logActivity('cancelled', $post);

        return redirect()->back()
            ->with('success', 'Post dibatalkan.');
    }

    /**
     * Publish a post directly to Threads via API.
     */
    public function publishToThreads(Post $post): RedirectResponse
    {
        $workspace = auth()->user()->workspace ?? null;

        if (!$workspace || empty($workspace->threads_access_token)) {
            return redirect()->back()
                ->with('error', 'Threads API belum dikonfigurasi. Tambahkan access token di Settings.');
        }

        if ($post->status === 'published') {
            return redirect()->back()
                ->with('error', 'Post sudah dipublish sebelumnya.');
        }

        // Dispatch job to publish async
        PublishToThreadsJob::dispatch($post);

        $this->logActivity('publish_queued', $post);

        return redirect()->back()
            ->with('success', 'Post sedang dipublish ke Threads... Anda akan mendapat notifikasi setelah selesai.');
    }

    // ─── Helpers ───

    private function logActivity(string $action, Post $post, array $extra = []): void
    {
        ActivityLog::create([
            'workspace_id' => $post->workspace_id ?? auth()->user()?->workspace_id,
            'user_id' => auth()->id(),
            'action' => $action,
            'subject_type' => Post::class,
            'subject_id' => $post->id,
            'properties' => array_filter([
                'status' => $post->status,
                ...$extra,
            ]),
        ]);
    }
}
