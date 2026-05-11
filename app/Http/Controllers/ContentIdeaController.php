<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\StoreContentIdeaRequest;
use App\Models\ContentIdea;
use App\Models\ContentPillar;
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
}
