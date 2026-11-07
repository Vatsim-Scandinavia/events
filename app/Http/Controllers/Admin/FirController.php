<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Fir;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Lets admins set up the FIRs the vACC covers. Each event belongs to one of them.
 *
 * A FIR that still owns events can't be deleted, so no event is left without one.
 */
class FirController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('Admin/Firs/Index', [
            'firs' => Fir::query()->withCount('events')->orderBy('icao')->get()->map(fn (Fir $fir) => [
                'id' => $fir->id,
                'icao' => $fir->icao,
                'name' => $fir->name,
                'events' => $fir->events_count,
            ]),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Admin/Firs/Form', ['fir' => null]);
    }

    public function store(Request $request): RedirectResponse
    {
        $fir = Fir::create($this->validated($request, null));

        Inertia::flash('success', "{$fir->icao} {$fir->name} was added.");

        return redirect('/admin/firs');
    }

    public function edit(Fir $fir): Response
    {
        return Inertia::render('Admin/Firs/Form', [
            'fir' => [
                'id' => $fir->id,
                'icao' => $fir->icao,
                'name' => $fir->name,
                'events' => $fir->events()->count(),
            ],
        ]);
    }

    public function update(Request $request, Fir $fir): RedirectResponse
    {
        $fir->update($this->validated($request, $fir));

        Inertia::flash('success', "{$fir->icao} {$fir->name} was saved.");

        return redirect('/admin/firs');
    }

    public function destroy(Fir $fir): RedirectResponse
    {
        $events = $fir->events()->count();

        if ($events > 0) {
            throw ValidationException::withMessages([
                'fir' => "{$fir->icao} runs {$events} ".Str::plural('event', $events).'. Move them to another FIR first.',
            ]);
        }

        $fir->delete();

        Inertia::flash('success', "{$fir->icao} {$fir->name} was deleted.");

        return redirect('/admin/firs');
    }

    /**
     * ICAO codes are accepted in any case and spacing.
     *
     * @return array{icao: string, name: string}
     */
    private function validated(Request $request, ?Fir $fir): array
    {
        $request->merge(['icao' => strtoupper(trim((string) $request->input('icao')))]);

        return $request->validate([
            'icao' => ['required', 'regex:/^[A-Z]{4}$/', Rule::unique('firs', 'icao')->ignore($fir)],
            'name' => ['required', 'string', 'max:60'],
        ], [
            'icao.required' => 'Enter the FIR’s ICAO code.',
            'icao.regex' => 'Use a four-letter ICAO code, like EKDK.',
            'icao.unique' => 'There’s already a FIR with this code.',
            'name.required' => 'Enter the FIR’s name.',
        ]);
    }
}
