<?php

namespace App\Http\Controllers;

use App\Actions\RecordAudit;
use App\Http\Requests\FirIndexRequest;
use App\Http\Requests\FirRequest;
use App\Models\Event;
use App\Models\EventCollaboration;
use App\Models\RoleGrant;
use App\Models\Team;
use App\PermissionName;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class FirController extends Controller
{
    public function __construct(private RecordAudit $audit) {}

    public function index(FirIndexRequest $request): Response
    {
        $search = trim($request->validated('search') ?? '');

        $firs = Team::query()
            ->select(['id', 'code', 'name'])
            ->addSelect(['members_count' => RoleGrant::query()
                ->selectRaw('count(distinct user_id)')->whereColumn('team_id', 'teams.id')])
            ->when($search !== '', fn (Builder $query): Builder => $query
                ->where(fn (Builder $query): Builder => $query
                    ->whereLike('code', '%'.$search.'%')->orWhereLike('name', '%'.$search.'%')))
            ->orderBy('code')->orderBy('id')
            ->paginate(15)->withQueryString()
            ->through(fn (Team $fir): array => [
                ...$fir->only(['id', 'code', 'name']),
                'members_count' => (int) $fir->getAttribute('members_count'),
            ]);

        return Inertia::render('firs/index', [
            'firs' => $firs,
            'filters' => ['search' => $search],
        ]);
    }

    public function store(FirRequest $request): RedirectResponse
    {
        DB::transaction(function () use ($request): void {
            $fir = Team::create($request->validated());
            $this->audit->handle($fir, 'created', [], $fir->only(['code', 'name']));
        });

        return to_route('firs.index');
    }

    public function update(FirRequest $request, Team $fir): RedirectResponse
    {
        DB::transaction(function () use ($request, $fir): void {
            $fir = Team::whereKey($fir->id)->lockForUpdate()->firstOrFail();
            $before = $fir->only(['code', 'name']);
            $fir->update($request->validated());
            $this->audit->handle($fir, 'updated', $before, $fir->only(['code', 'name']));
        });

        return back();
    }

    public function destroy(Team $fir): RedirectResponse
    {
        Gate::authorize(PermissionName::ManageFirs);

        DB::transaction(function () use ($fir): void {
            $fir = Team::whereKey($fir->id)->lockForUpdate()->firstOrFail();

            if (RoleGrant::where('team_id', $fir->id)->exists()) {
                throw ValidationException::withMessages([
                    'fir' => 'Remove all role assignments from this FIR before deleting it, including assignments from external sources.',
                ]);
            }

            if (Event::where('owner_team_id', $fir->id)->exists() || EventCollaboration::where('team_id', $fir->id)->exists()) {
                throw ValidationException::withMessages(['fir' => 'This FIR owns events or has event collaborations and cannot be deleted.']);
            }

            $fir->delete();
            $this->audit->handle($fir, 'deleted', $fir->only(['code', 'name']), []);
        });

        return to_route('firs.index');
    }
}
