# Sign-in goes back to Laravel Socialite, with one provider class per Sign-in Provider

Members sign in through VATSIM Connect or VATSCA Handover using Laravel Socialite, the approach Laravel documents. We drop the OAuth2 client we wrote ourselves (ADR-0001). Most of its reasons no longer hold. Socialite turns on S256 PKCE with one property, ships `Socialite::fake()`, and can be faked at the HTTP layer too (below). One reason still stands: Socialite's `refreshToken()` throws a `TypeError` when the provider sends no new refresh token, so each provider overrides it to keep the old one. Owning the protocol code cost more to maintain than it saved. Connect and Handover each get their own provider class, even though their endpoints match today, so a change to one can never break the other.

## Consequences

- Each provider builds its Guzzle client on `Http::buildHandlerStack()`, so `Http::fake` covers the real exchange end to end: redirect parameters, code exchange, `userFromToken()` and refresh. That keeps ADR-0001's best point, testing at the HTTP boundary. `Socialite::fake()` alone would only stand in for `redirect()` and `user()`.
- We still keep each Account's tokens, so the daily background sync can keep email and name fresh without the Member signing in again. Connect is sent `required_scopes`, so Members can't untick email or VATSIM details. Connect has no parameter that forces continuous access, so, as in Handover, sign-in and the sync check `oauth.token_valid` and turn away anything incomplete.
- Accounts whose token has died stop syncing until their next sign-in. There's no fallback to VATSIM's public member API. Their last-known rating and subdivision, and so their Controller Rule status, stay until then.
- Socialite's manager keeps each driver, along with its request and the last signed-in member, for the life of the process. `AppServiceProvider` forgets them whenever a new request is bound, so tests and Octane don't sign in the previous member.
