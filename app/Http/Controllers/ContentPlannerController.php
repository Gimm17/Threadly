<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\ContentIdea;
use App\Models\ContentPillar;
use App\Models\Post;
use Illuminate\Http\JsonResponse;
use Inertia\Inertia;
use Inertia\Response;

class ContentPlannerController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('ContentPlanner/Index', [
            'pillars' => ContentPillar::active()
                ->ordered()
                ->withCount(['posts', 'contentIdeas'])
                ->get(),
            'ideas' => ContentIdea::with('contentPillar')
                ->whereIn('status', ['draft', 'in_progress'])
                ->latest()
                ->limit(10)
                ->get(),
        ]);
    }

    public function calendarData(): JsonResponse
    {
        $month = request('month', now()->month);
        $year = request('year', now()->year);

        $startDate = \Carbon\Carbon::create($year, $month, 1)->startOfMonth();
        $endDate = $startDate->copy()->endOfMonth();

        $posts = Post::with('contentPillar')
            ->whereBetween('scheduled_at', [$startDate, $endDate])
            ->orWhereBetween('published_at', [$startDate, $endDate])
            ->get()
            ->map(fn ($post) => [
                'id' => $post->id,
                'title' => $post->hook ?? mb_substr($post->body, 0, 30) . '...',
                'date' => ($post->scheduled_at ?? $post->published_at)->toDateString(),
                'status' => $post->status,
                'pillar' => $post->contentPillar?->name,
                'pillar_color' => $post->contentPillar?->color_hex,
            ]);

        $ideas = ContentIdea::with('contentPillar')
            ->whereBetween('target_date', [$startDate, $endDate])
            ->whereIn('status', ['draft', 'in_progress'])
            ->get()
            ->map(fn ($idea) => [
                'id' => $idea->id,
                'title' => mb_substr($idea->title, 0, 30) . '...',
                'date' => $idea->target_date->toDateString(),
                'status' => 'idea_' . $idea->status,
                'pillar' => $idea->contentPillar?->name,
                'pillar_color' => $idea->contentPillar?->color_hex,
            ]);

        return response()->json([
            'events' => $posts->merge($ideas)->groupBy('date'),
        ]);
    }
}
