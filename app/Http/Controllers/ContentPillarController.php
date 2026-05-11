<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\StoreContentPillarRequest;
use App\Models\ContentPillar;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class ContentPillarController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('ContentPlanner/Index', [
            'pillars' => ContentPillar::active()
                ->ordered()
                ->withCount(['posts', 'contentIdeas'])
                ->get(),
        ]);
    }

    public function store(StoreContentPillarRequest $request): RedirectResponse
    {
        ContentPillar::create($request->validated());

        return redirect()->route('content-planner')
            ->with('success', 'Pilar konten berhasil dibuat.');
    }

    public function update(StoreContentPillarRequest $request, ContentPillar $contentPillar): RedirectResponse
    {
        $contentPillar->update($request->validated());

        return redirect()->route('content-planner')
            ->with('success', 'Pilar konten berhasil diperbarui.');
    }

    public function destroy(ContentPillar $contentPillar): RedirectResponse
    {
        $contentPillar->delete();

        return redirect()->route('content-planner')
            ->with('success', 'Pilar konten berhasil dihapus.');
    }
}
