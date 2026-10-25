<?php

namespace App\Integrations;

use App\Enums\IntegrationRunStatus;
use App\Models\IntegrationRun;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Runs one feature's sync and records how it went. A sync that throws or holds changes
 * nothing, so an outage or a bug upstream never takes anything away. Failures aren't retried:
 * the next scheduled run, or an admin's "Sync now", tries again.
 */
class RunSync implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    /**
     * @param  ?int  $approvedBy  The Administrator letting a held run's removals through.
     */
    public function __construct(
        public string $integration,
        public string $feature,
        public ?int $approvedBy = null,
    ) {}

    /**
     * @return list<object>
     */
    public function middleware(): array
    {
        return [(new WithoutOverlapping("{$this->integration}.{$this->feature}"))->releaseAfter(60)->expireAfter(3600)];
    }

    public function handle(Integrations $integrations): void
    {
        $integration = $integrations->find($this->integration);
        $feature = $integration?->feature($this->feature);

        // Switched off, or no longer offered, since it was queued.
        if (! $feature instanceof Syncable || ! $integrations->isOn($integration, $feature)) {
            return;
        }

        $run = IntegrationRun::query()->create([
            'integration' => $this->integration,
            'feature' => $this->feature,
            'status' => IntegrationRunStatus::Running,
            'approved_by' => $this->approvedBy,
            'started_at' => now(),
        ]);

        $context = new SyncContext($integrations->configuration($integration), approved: $this->approvedBy !== null);

        DB::beginTransaction();

        try {
            $result = $feature->sync($context);
        } catch (Throwable $exception) {
            DB::rollBack();
            Log::warning('An integration sync failed.', ['run' => $run->id, 'exception' => $exception]);

            $run->update([
                'status' => IntegrationRunStatus::Failed,
                'message' => $exception instanceof ConnectionFailed ? $exception->getMessage() : 'The sync broke off. Nothing was changed; the details are in the log.',
                'finished_at' => now(),
            ]);

            return;
        }

        // Holding means nothing changed, whatever the sync wrote before deciding to hold.
        if ($result->status === IntegrationRunStatus::Held) {
            DB::rollBack();
        } else {
            DB::commit();
        }

        $run->update([
            'status' => $result->status,
            'added' => $result->added,
            'updated' => $result->updated,
            'removed' => $result->removed,
            'message' => $result->message,
            'warnings' => $result->warnings ?: null,
            'finished_at' => now(),
        ]);
    }
}
