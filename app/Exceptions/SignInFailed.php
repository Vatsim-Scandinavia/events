<?php

namespace App\Exceptions;

use App\Enums\SignInFailure;
use App\Enums\SignInProvider;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Exception\TransferException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Inertia\Inertia;
use RuntimeException;

/**
 * A sign-in that didn't go through, and why. Thrown anywhere in sign-in, it lands the Member back
 * on the page they started from with the reason and, unless trying again can't help, a "Try again"
 * link. Only a provider that failed is logged; the other reasons are the Member's own doing.
 *
 * It never carries a token or a code, so it is safe to log.
 */
class SignInFailed extends RuntimeException
{
    /**
     * @param  int|null  $status  The HTTP status the provider answered, when it failed.
     * @param  string|null  $error  The OAuth error code it sent, such as "invalid_grant".
     */
    public function __construct(public readonly SignInFailure $reason, public readonly ?int $status = null, public readonly ?string $error = null)
    {
        parent::__construct("Sign-in failed: {$reason->value}.");
    }

    /**
     * The provider timed out, failed, or wouldn't swap the code for tokens.
     */
    public static function providerUnavailable(TransferException $exception): self
    {
        $response = $exception instanceof RequestException ? $exception->getResponse() : null;
        $error = $response !== null ? (json_decode((string) $response->getBody(), true)['error'] ?? null) : null;

        return new self(SignInFailure::ProviderUnavailable, $response?->getStatusCode(), is_string($error) ? Str::limit($error, 50, '') : null);
    }

    public function report(): void
    {
        if ($this->reason === SignInFailure::ProviderUnavailable) {
            Log::warning('The sign-in provider could not complete a sign-in.', [
                'provider' => SignInProvider::configured()->value,
                'status' => $this->status,
                'error' => $this->error,
            ]);
        }
    }

    public function render(Request $request): RedirectResponse
    {
        if ($this->reason->offersRetry()) {
            Inertia::flash('signInRetry', true);
        }

        return redirect()->intended('/')->withErrors(['signIn' => $this->reason->message(SignInProvider::configured())]);
    }
}
