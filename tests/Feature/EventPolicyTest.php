<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\Fir;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class EventPolicyTest extends TestCase
{
    use RefreshDatabase;

    private const array ABILITIES = ['update', 'delete', 'cancel', 'manageStaffing', 'viewStaffing'];

    private const array EVERYTHING = self::ABILITIES;

    private const array VIEW_ONLY = ['viewStaffing'];

    /**
     * Who holds what, which event they act on (one run by EKDK or by ESAA), and
     * the abilities they should have on it.
     *
     * @return array<string, array{string, string, list<string>}>
     */
    public static function matrix(): array
    {
        return [
            'EKDK Event Staff on an EKDK event' => ['ekdk-staff', 'EKDK', self::EVERYTHING],
            'EKDK Event Staff on an ESAA event' => ['ekdk-staff', 'ESAA', []],
            'hub-wide Event Staff on an ESAA event' => ['hub-staff', 'ESAA', self::EVERYTHING],
            'EKDK vACC Staff on an EKDK event' => ['ekdk-vacc', 'EKDK', self::VIEW_ONLY],
            'EKDK vACC Staff on an ESAA event' => ['ekdk-vacc', 'ESAA', []],
            'hub-wide vACC Staff on an ESAA event' => ['hub-vacc', 'ESAA', self::VIEW_ONLY],
            'Administrator on an ESAA event' => ['administrator', 'ESAA', self::EVERYTHING],
            'pilot on an EKDK event' => ['pilot', 'EKDK', []],
        ];
    }

    /**
     * @param  list<string>  $allowed
     */
    #[DataProvider('matrix')]
    public function test_what_people_may_do_depends_on_the_fir_running_the_event(string $holder, string $runBy, array $allowed): void
    {
        $ekdk = Fir::factory()->create(['icao' => 'EKDK']);
        $esaa = Fir::factory()->create(['icao' => 'ESAA']);

        $user = match ($holder) {
            'ekdk-staff' => User::factory()->staff($ekdk)->create(),
            'hub-staff' => User::factory()->staff()->create(),
            'ekdk-vacc' => User::factory()->vaccStaff($ekdk)->create(),
            'hub-vacc' => User::factory()->vaccStaff()->create(),
            'administrator' => User::factory()->admin()->create(),
            'pilot' => User::factory()->create(),
        };

        $event = Event::factory()->create(['fir_id' => match ($runBy) {
            'EKDK' => $ekdk->id,
            'ESAA' => $esaa->id,
        }]);

        $granted = array_values(array_filter(self::ABILITIES, fn (string $ability) => Gate::forUser($user)->allows($ability, $event)));

        $this->assertSame($allowed, $granted);
    }
}
