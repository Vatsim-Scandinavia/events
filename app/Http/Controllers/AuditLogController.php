<?php

namespace App\Http\Controllers;

use App\Http\Requests\AuditLogIndexRequest;
use App\Models\AuditLog;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Inertia\Inertia;
use Inertia\Response;

class AuditLogController extends Controller
{
    public function index(AuditLogIndexRequest $request): Response
    {
        $filters = [
            'search' => trim($request->validated('search') ?? ''),
            'subject_type' => $request->validated('subject_type') ?? '',
            'event' => $request->validated('event') ?? '',
            'from' => $request->validated('from') ?? '',
            'to' => $request->validated('to') ?? '',
        ];

        $logs = AuditLog::query()
            ->when($filters['search'] !== '', fn (Builder $query): Builder => $query
                ->where(function (Builder $query) use ($filters): void {
                    $query->whereLike('actor_name', '%'.$filters['search'].'%')
                        ->orWhereLike('subject_label', '%'.$filters['search'].'%')
                        ->orWhereLike('source', '%'.$filters['search'].'%');

                    if (ctype_digit($filters['search'])) {
                        $query->orWhere('actor_cid', $filters['search'])->orWhere('subject_id', $filters['search']);
                    }
                }))
            ->when($filters['subject_type'] !== '', fn (Builder $query): Builder => $query->where('subject_type', $filters['subject_type']))
            ->when($filters['event'] !== '', fn (Builder $query): Builder => $query->where('event', $filters['event']))
            ->when($filters['from'] !== '', fn (Builder $query): Builder => $query->where('created_at', '>=', $filters['from'].' 00:00:00'))
            ->when($filters['to'] !== '', fn (Builder $query): Builder => $query->where('created_at', '<', CarbonImmutable::parse($filters['to'], 'UTC')->addDay()->format('Y-m-d').' 00:00:00'))
            ->orderByDesc('created_at')->orderByDesc('id')
            ->paginate(20)->withQueryString();

        return Inertia::render('audit-logs/index', [
            'logs' => $logs,
            'filters' => $filters,
        ]);
    }
}
