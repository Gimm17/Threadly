<?php

declare(strict_types=1);

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Models\Workspace;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class GeneralController extends Controller
{
    public function index(): Response
    {
        $workspace = Workspace::find(auth()->user()->workspace_id);

        return Inertia::render('Settings/General', [
            'workspace' => [
                'id' => $workspace->id,
                'name' => $workspace->name,
                'threads_handle' => $workspace->threads_handle,
                'timezone' => $workspace->timezone ?? 'Asia/Makassar',
                'plan' => $workspace->plan ?? 'free',
                'reminder_offset' => config('threadly.reminder_offset_minutes', 30),
            ],
            'timezones' => $this->getTimezoneOptions(),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'threads_handle' => ['nullable', 'string', 'max:100'],
            'timezone' => ['required', 'string', 'timezone'],
        ]);

        $workspace = Workspace::find(auth()->user()->workspace_id);
        $workspace->update($validated);

        return redirect()->back()
            ->with('success', 'Pengaturan workspace berhasil diperbarui.');
    }

    private function getTimezoneOptions(): array
    {
        return [
            ['value' => 'Asia/Jakarta', 'label' => 'WIB (Jakarta) — UTC+7'],
            ['value' => 'Asia/Makassar', 'label' => 'WITA (Makassar) — UTC+8'],
            ['value' => 'Asia/Jayapura', 'label' => 'WIT (Jayapura) — UTC+9'],
            ['value' => 'Asia/Singapore', 'label' => 'SGT (Singapore) — UTC+8'],
            ['value' => 'Asia/Tokyo', 'label' => 'JST (Tokyo) — UTC+9'],
            ['value' => 'UTC', 'label' => 'UTC — UTC+0'],
        ];
    }
}
