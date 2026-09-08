<?php

namespace App\Http\Controllers;

use App\Actions\RecordAudit;
use App\Http\Requests\AirportIndexRequest;
use App\Http\Requests\AirportRequest;
use App\Models\Airport;
use App\Models\Event;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class AirportController extends Controller
{
    public function index(AirportIndexRequest $request): Response|JsonResponse
    {
        $search = $request->validated('search') ?? '';
        $query = Airport::query()->orderBy('icao');

        if ($request->wantsJson()) {
            return response()->json(['airport' => $request->filled('icao')
                ? $query->where('icao', $request->validated('icao'))->first(['id', 'icao', 'name', 'country'])
                : null]);
        }

        return Inertia::render('airports/index', [
            'airports' => $query->when($search !== '', fn (Builder $query) => $query
                ->where(fn (Builder $query) => $query->whereLike('icao', '%'.$search.'%')->orWhereLike('name', '%'.$search.'%')
                    ->orWhereLike('country', '%'.$search.'%')))
                ->paginate(20)->withQueryString(),
            'filters' => ['search' => $search],
            'can_create' => $request->user()->can('create', Event::class),
        ]);
    }

    public function store(AirportRequest $request, RecordAudit $audit): JsonResponse
    {
        $airport = DB::transaction(function () use ($request, $audit): Airport {
            $airport = Airport::create($request->validated());
            $audit->handle($airport, 'created', [], $airport->only(['icao', 'name', 'country']));

            return $airport;
        });

        return response()->json(['airport' => $airport->only(['id', 'icao', 'name', 'country'])], 201);
    }
}
