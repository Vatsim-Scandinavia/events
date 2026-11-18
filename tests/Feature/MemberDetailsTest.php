<?php

namespace Tests\Feature;

use App\Auth\Accounts;
use App\Auth\MemberDetails;
use App\Enums\ControllerRating;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Tests\TestCase;

class MemberDetailsTest extends TestCase
{
    use RefreshDatabase;

    public function test_signing_in_maps_a_cid_sent_as_a_string_or_an_integer(): void
    {
        $this->assertSame(10000010, MemberDetails::fromSignIn(['cid' => '10000010'])?->cid);
        $this->assertSame(10000010, MemberDetails::fromSignIn(['cid' => 10000010])?->cid);
        $this->assertNull(MemberDetails::fromSignIn(['personal' => ['name_full' => 'No CID']]));
    }

    public function test_a_member_who_did_not_share_vatsim_details_is_told_apart(): void
    {
        $this->assertFalse(MemberDetails::fromSignIn(['cid' => '1000001', 'personal' => ['name_full' => 'Kim']])->sharedVatsimDetails);
        $this->assertTrue($this->member()->sharedVatsimDetails);
    }

    public function test_applying_creates_the_account_on_first_sign_in_and_overwrites_it_after(): void
    {
        $accounts = app(Accounts::class);

        $user = $accounts->apply($this->member());
        $this->assertSame(['Kim Controller', 'Kim', 'kim@example.com', ControllerRating::S2, 'SCA'], [$user->name, $user->first_name, $user->email, $user->rating, $user->subdivision]);

        $accounts->apply($this->member(name: null, email: null, rating: ControllerRating::C1->value, subdivision: null));
        $user->refresh();
        $this->assertSame(['VATSIM 1000001', null, ControllerRating::C1, null], [$user->name, $user->email, $user->rating, $user->subdivision]);
        $this->assertSame(1, User::query()->count());
    }

    public function test_a_rating_the_hub_does_not_know_keeps_the_one_it_has(): void
    {
        $accounts = app(Accounts::class);
        $accounts->apply($this->member());

        $this->assertSame(ControllerRating::S2, $accounts->apply($this->member(rating: 99))->rating);
    }

    public function test_partial_details_only_change_rating_and_subdivision(): void
    {
        $accounts = app(Accounts::class);
        $user = $accounts->apply($this->member());

        $accounts->apply(MemberDetails::fromPublicRecord(1000001, ['id' => 1000001, 'rating' => ControllerRating::S3->value, 'subdivision_id' => null]));
        $user->refresh();

        $this->assertSame(['Kim Controller', 'kim@example.com', ControllerRating::S3, null], [$user->name, $user->email, $user->rating, $user->subdivision]);
        $this->assertNull(MemberDetails::fromPublicRecord(1000001, ['id' => 1000001]));
    }

    public function test_partial_details_never_create_an_account(): void
    {
        $this->expectException(InvalidArgumentException::class);

        app(Accounts::class)->apply(MemberDetails::fromPublicRecord(1000001, ['rating' => ControllerRating::S1->value]));
    }

    public function test_suspended_and_inactive_members_are_recognised(): void
    {
        $this->assertTrue($this->member(rating: ControllerRating::Suspended->value)->isSuspended());
        $this->assertTrue($this->member(rating: ControllerRating::Inactive->value)->isSuspended());
        $this->assertFalse($this->member()->isSuspended());
    }

    private function member(?string $name = 'Kim Controller', ?string $email = 'kim@example.com', int $rating = 3, ?string $subdivision = 'SCA'): MemberDetails
    {
        return MemberDetails::fromSignIn([
            'cid' => '1000001',
            'personal' => ['name_first' => $name === null ? null : 'Kim', 'name_full' => $name, 'email' => $email],
            'vatsim' => ['rating' => ['id' => $rating], 'subdivision' => ['id' => $subdivision]],
        ]);
    }
}
