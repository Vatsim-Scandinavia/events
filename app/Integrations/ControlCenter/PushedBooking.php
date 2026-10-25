<?php

namespace App\Integrations\ControlCenter;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;

/**
 * A Shift Booking or published Roster Entry as the Hub last sent it to Control Center: the
 * booking it became there, or why Control Center turned it down.
 *
 * @property int $event_occurrence_id
 * @property string $source
 * @property int $source_id
 * @property ?int $upstream_id
 * @property ?int $cid
 * @property string $callsign
 * @property CarbonImmutable $starts_at
 * @property CarbonImmutable $ends_at
 * @property string $status
 * @property ?string $reason
 * @property bool $retryable
 */
class PushedBooking extends Model
{
    public const string SENT = 'sent';

    public const string NOT_SENT = 'not_sent';

    protected $table = 'control_center_bookings';

    /**
     * @var list<string>
     */
    protected $fillable = ['event_occurrence_id', 'source', 'source_id', 'upstream_id', 'cid', 'callsign', 'starts_at', 'ends_at', 'status', 'reason', 'retryable'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'upstream_id' => 'integer',
            'cid' => 'integer',
            'starts_at' => 'immutable_datetime',
            'ends_at' => 'immutable_datetime',
            'retryable' => 'boolean',
        ];
    }

    public function isSent(): bool
    {
        return $this->status === self::SENT && $this->upstream_id !== null;
    }

    /**
     * Whether Control Center has a booking for it, even one a later change couldn't reach.
     */
    public function isUpstream(): bool
    {
        return $this->upstream_id !== null;
    }

    /**
     * Whether it was last sent, or tried, with exactly these details.
     *
     * @param  array{cid: ?int, callsign: string, starts_at: CarbonImmutable, ends_at: CarbonImmutable}  $booking
     */
    public function matches(array $booking): bool
    {
        return $this->cid === $booking['cid']
            && $this->callsign === $booking['callsign']
            && $this->starts_at->equalTo($booking['starts_at'])
            && $this->ends_at->equalTo($booking['ends_at']);
    }
}
