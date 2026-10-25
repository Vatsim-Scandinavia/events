<?php

namespace App\Integrations\ControlCenter;

use App\Integrations\ConnectionFailed;
use App\Integrations\Integrations;
use App\Models\EventOccurrence;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Queue\ShouldBeUniqueUntilProcessing;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Middleware\WithoutOverlapping;

/**
 * Brings one date's bookings in Control Center in line with its staffing: sends new ones,
 * moves changed ones and deletes those no longer wanted. Each booking is recorded as soon as
 * Control Center answers, so a retry after Control Center went down picks up where it stopped.
 *
 * A booking Control Center turns down is recorded as not sent, with its reason, and isn't sent
 * again until it changes (or, when VATSIM failed behind Control Center, at the next sweep).
 */
class PushBookings implements ShouldBeUniqueUntilProcessing, ShouldQueue
{
    use Queueable;

    public int $tries = 5;

    /** Control Center is down, or slow: wait longer each time. */
    public array $backoff = [30, 120, 600, 1800];

    public function __construct(public int $occurrenceId) {}

    public function uniqueId(): string
    {
        return (string) $this->occurrenceId;
    }

    /**
     * @return list<object>
     */
    public function middleware(): array
    {
        return [(new WithoutOverlapping((string) $this->occurrenceId))->releaseAfter(30)->expireAfter(600)];
    }

    /**
     * @throws ConnectionFailed to try again later
     */
    public function handle(Integrations $integrations): void
    {
        $controlCenter = app(ControlCenter::class);
        $feature = $controlCenter->feature('bookings');

        if ($feature === null || ! $integrations->isOn($controlCenter, $feature)) {
            return;
        }

        $api = new ControlCenterApi($integrations->configuration($controlCenter));
        $wanted = BookingPush::wanted(EventOccurrence::query()->with('event')->find($this->occurrenceId));
        $pushed = PushedBooking::query()
            ->where('event_occurrence_id', $this->occurrenceId)
            ->get()
            ->keyBy(fn (PushedBooking $booking) => "{$booking->source}:{$booking->source_id}");

        foreach ($pushed as $key => $booking) {
            if (! $wanted->has($key)) {
                $this->withdraw($api, $booking);
            }
        }

        foreach ($wanted as $key => $booking) {
            $this->send($api, $pushed->get($key) ?? new PushedBooking([
                'event_occurrence_id' => $this->occurrenceId,
                'source' => $booking['source'],
                'source_id' => $booking['source_id'],
            ]), $booking);
        }
    }

    /**
     * Delete it in Control Center, unless it has started: then it's history, and Control Center
     * keeps it.
     */
    private function withdraw(ControlCenterApi $api, PushedBooking $booking): void
    {
        if ($booking->isUpstream() && $booking->starts_at->isFuture()) {
            try {
                $api->deleteBooking($booking->upstream_id);
            } catch (BookingRefused $refused) {
                $booking->update(['reason' => "Couldn’t delete it: {$refused->getMessage()}"]);

                return;
            }
        }

        $booking->delete();
    }

    /**
     * @param  array{source: string, source_id: int, cid: ?int, callsign: string, starts_at: CarbonImmutable, ends_at: CarbonImmutable}  $wanted
     */
    private function send(ControlCenterApi $api, PushedBooking $booking, array $wanted): void
    {
        $unchanged = $booking->exists && $booking->matches($wanted);

        if ($unchanged && ($booking->isSent() || ! $booking->retryable)) {
            return;
        }

        $details = ['cid' => $wanted['cid'], 'callsign' => $wanted['callsign'], 'starts_at' => $wanted['starts_at'], 'ends_at' => $wanted['ends_at']];

        try {
            $upstreamId = $this->upstream($api, $booking, $wanted);
            $booking->fill([...$details, 'upstream_id' => $upstreamId, 'status' => PushedBooking::SENT, 'reason' => null, 'retryable' => false])->save();
        } catch (BookingRefused $refused) {
            // A booking that was sent before stays in Control Center as it was; say so.
            $booking->fill([
                ...$details,
                'status' => PushedBooking::NOT_SENT,
                'reason' => $refused->getMessage(),
                'retryable' => $refused->retryable,
            ])->save();
        }
    }

    /**
     * Make or move the booking in Control Center, and return its id there.
     *
     * @param  array{cid: ?int, callsign: string, starts_at: CarbonImmutable, ends_at: CarbonImmutable}  $wanted
     *
     * @throws BookingRefused
     * @throws ConnectionFailed
     */
    private function upstream(ControlCenterApi $api, PushedBooking $booking, array $wanted): int
    {
        if ($wanted['cid'] === null) {
            throw new BookingRefused('The controller has no VATSIM CID.');
        }

        if ($wanted['starts_at']->diffInMinutes($wanted['ends_at']) >= 24 * 60) {
            throw new BookingRefused('Control Center can’t take bookings of 24 hours or more.');
        }

        $payload = BookingPush::payload($wanted);

        if ($booking->isUpstream()) {
            // Control Center can't hand a booking to someone else, so that's a new booking.
            if ($booking->cid === $wanted['cid'] && $api->updateBooking($booking->upstream_id, $payload)) {
                return $booking->upstream_id;
            }

            if ($booking->cid !== $wanted['cid']) {
                $api->deleteBooking($booking->upstream_id);
            }
        }

        return $api->createBooking($payload);
    }
}
