<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\GeneratedMedia;
use App\Models\HookTemplate;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class LibraryController extends Controller
{
    public function index(Request $request): Response
    {
        $query = HookTemplate::query();

        if ($request->filled('category')) {
            $query->forCategory($request->category);
        }

        $sort = $request->get('sort', 'score');
        $hooks = match ($sort) {
            'saves' => $query->bySaveRate()->paginate(18)->withQueryString(),
            'newest' => $query->latest()->paginate(18)->withQueryString(),
            default => $query->topPerforming(100)->paginate(18)->withQueryString(),
        };

        return Inertia::render('Library/Index', [
            'hooks' => $hooks,
            'categories' => HookTemplate::query()
                ->select('category')
                ->distinct()
                ->whereNotNull('category')
                ->pluck('category'),
            'media' => GeneratedMedia::where('type', 'image')
                ->orderByDesc('created_at')
                ->paginate(12, ['*'], 'media_page')
                ->withQueryString(),
            'filters' => [
                'tab' => $request->get('tab', 'hooks'),
                'category' => $request->get('category'),
                'sort' => $sort,
            ],
        ]);
    }
}
