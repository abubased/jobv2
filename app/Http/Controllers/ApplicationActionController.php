<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Application;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ApplicationActionController extends Controller
{
    public function update(Request $request, Application $application): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'phone' => ['required', 'string', 'max:40'],
            'address' => ['nullable', 'string', 'max:2000'],
        ]);

        $application->update($validated);

        return back()->with('success', 'Application information updated.');
    }

    public function toggleBlock(Application $application): RedirectResponse
    {
        $application->update(['is_blocked' => ! $application->is_blocked]);

        return back()->with('success', $application->is_blocked
            ? 'Application blocked successfully.'
            : 'Application unblocked successfully.');
    }

    public function destroy(Application $application): RedirectResponse
    {
        // The current Application model declares no dependent relationships.
        // Delete only this row; database constraints remain authoritative.
        DB::transaction(function () use ($application): void {
            $application->newQuery()->whereKey($application->getKey())->lockForUpdate()->firstOrFail()->delete();
        });

        return redirect()->route('all-applications.index')->with('success', 'Application deleted.');
    }

    public function addLabel(Request $request, Application $application): RedirectResponse
    {
        $validated = $request->validate([
            'label' => ['required', 'string', 'max:60'],
        ]);

        DB::transaction(function () use ($application, $validated): void {
            $locked = Application::query()->lockForUpdate()->findOrFail($application->id);
            $labels = is_array($locked->labels) ? $locked->labels : [];
            $label = trim($validated['label']);
            if (! in_array($label, $labels, true)) {
                $labels[] = $label;
            }
            $locked->labels = array_values($labels);
            $locked->save();
        });

        return back()->with('success', 'Label added.');
    }

    public function addNote(Request $request, Application $application): RedirectResponse
    {
        $validated = $request->validate([
            'note' => ['required', 'string', 'max:5000'],
        ]);

        DB::transaction(function () use ($application, $validated): void {
            $locked = Application::query()->lockForUpdate()->findOrFail($application->id);
            $notes = is_array($locked->notes) ? $locked->notes : [];
            $notes[] = [
                'text' => trim($validated['note']),
                'created_at' => now()->toIso8601String(),
            ];
            $locked->notes = array_slice($notes, -100);
            $locked->save();
        });

        return back()->with('success', 'Note added.');
    }
}
