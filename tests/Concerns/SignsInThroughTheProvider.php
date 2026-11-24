<?php

namespace Tests\Concerns;

use App\Enums\ControllerRating;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Testing\TestResponse;

/**
 * Signs in through the real flow (start, then the provider's callback) against a faked Sign-in
 * Provider, so the state check, the code exchange and the /api/user mapping all run.
 *
 * The fake is registered once per test and answers for whichever member signs in next, since a
 * second Http::fake would add to the first rather than replace it.
 */
trait SignsInThroughTheProvider
{
    /** @var array<string, mixed>|null What the provider's /api/user answers next. */
    private ?array $providerMember = null;

    /** @var array<string, mixed> What the provider's token endpoint answers next. */
    private array $providerTokens = [];

    private bool $providerFaked = false;

    /**
     * Sign in as the member through the configured provider, from the given page, and return the
     * callback's response. The session is signed out again unless $stay.
     *
     * @param  array<string, mixed>  $member  The `data` of /api/user, e.g. from connectMember().
     */
    protected function signInThroughProvider(array $member, ?string $returnTo = null, bool $stay = true): TestResponse
    {
        $provider = (string) config('auth.sign_in_provider');
        $this->fakeTheProvider();
        $this->providerMember = $member;

        $this->get("/auth/{$provider}".($returnTo !== null ? '?return='.urlencode($returnTo) : ''));
        $response = $this->get("/auth/{$provider}/callback?code=the-code&state=".session('state'));

        if (! $stay) {
            auth()->logout();
        }

        return $response;
    }

    /**
     * What the token endpoint answers from now on, e.g. an error.
     *
     * @param  array<string, mixed>  $body
     */
    protected function providerTokensAnswer(array $body, int $status = 200): void
    {
        $this->fakeTheProvider();
        $this->providerTokens = ['body' => $body, 'status' => $status];
    }

    /**
     * A member as /api/user describes them, with every scope and continuous access shared.
     *
     * @return array<string, mixed>
     */
    protected static function connectMember(int $cid, ControllerRating $rating = ControllerRating::S2, ?string $subdivision = 'SCA', string $name = 'Kim Controller', ?string $firstName = 'Kim', ?string $email = 'kim@example.com'): array
    {
        return [
            'cid' => (string) $cid,
            'personal' => ['name_first' => $firstName, 'name_last' => 'Controller', 'name_full' => $name, 'email' => $email],
            'vatsim' => [
                'rating' => ['id' => $rating->value, 'short' => $rating->label()],
                'subdivision' => ['id' => $subdivision, 'name' => $subdivision === null ? null : 'Some subdivision'],
            ],
            'oauth' => ['token_valid' => 'true'],
        ];
    }

    private function fakeTheProvider(): void
    {
        if ($this->providerFaked) {
            return;
        }

        $this->providerFaked = true;
        $this->providerTokens = ['body' => ['token_type' => 'Bearer', 'expires_in' => 604800, 'access_token' => 'access-token', 'refresh_token' => 'refresh-token'], 'status' => 200];

        Http::fake(function (Request $request) {
            if (str_ends_with($request->url(), '/oauth/token')) {
                return Http::response($this->providerTokens['body'], $this->providerTokens['status']);
            }

            if (str_ends_with($request->url(), '/api/user')) {
                return Http::response(['data' => $this->providerMember]);
            }

            return null;
        });
    }
}
