# What VATSIM Connect and Handover allow with OAuth tokens

Research for the ticket "Research: what VATSIM Connect and Handover allow with OAuth tokens" (#233), under the map #232. Checked 2026-11-15.

**Question:** can the Event Hub keep user details (rating, subdivision) fresh with stored OAuth tokens instead of the public `api.vatsim.net/v2/members/{cid}`, for both Sign-in providers?

**Short answer:** yes, mostly. VATSIM Connect issues refresh tokens and documents the `refresh_token` grant as normal client use. Handover does too, with refresh tokens that effectively never expire. Two caveats change the picture:

1. **Handover's `/api/user` is not live.** It returns Handover's own copy of the member, which Handover refreshes once a day from Connect, using Connect refresh tokens it keeps for itself. So for Handover users we can't get fresher data than about one day, and we depend on Handover's own token chain still working.
2. **Connect refresh tokens are documented as valid for 30 days.** Someone who never signs in again keeps their token chain only if we refresh at least once every 30 days, and only if Connect hands back a new refresh token each time. That second part is unconfirmed, though Handover's own code assumes it.

VATSIM also documents **webhooks** (`member_changed_action`, with rating and subdivision deltas) as "the recommended way to subscribe to changes", replacing polling. Access is by request to the tech team for now. This is worth considering before building a token-based poller.

Sources are listed at the end. "Unconfirmed" means no primary source settled it.

---

## 1. Refresh tokens: issued? lifetimes? rotation?

### VATSIM Connect (auth.vatsim.net)

- **Issued: yes.** In the Connect OpenAPI spec, the `/oauth/token` response schema has a `refresh_token` field ("Refresh token (valid for 30 days)"). The `grant_type` description says "`authorization_code` and `refresh_token` are used by most clients". [S1]
- **For which scopes:** the spec doesn't tie refresh tokens to any scope. The documented scopes are `full_name`, `email`, `vatsim_details` and `country`, and there's no `offline_access`-style scope. On a refresh, `scope` is "Optional when using the `refresh_token` grant". [S1] So our current scopes (`full_name vatsim_details email`) should be enough. *Unconfirmed in practice:* whether every client or organisation gets refresh tokens.
- **Access token lifetime:** `expires_in` is described as "Access token lifetime (secs)", with the example `604800` (7 days). [S1] That's an example value, not a promise. Read `expires_in` from each response.
- **Refresh token lifetime:** "valid for 30 days". [S1]
- **Rotation: unconfirmed.** The spec doesn't say whether a refresh returns a new refresh token or invalidates the old one. Two pieces of indirect evidence:
  - Connect's error bodies have the same shape as Laravel Passport / league/oauth2-server ones (`{"error":"invalid_client","error_description":"Client authentication failed","message":"Client authentication failed"}` from `/oauth/token`, and `{"message":"Unauthenticated."}` from `/api/user`), checked by probing the live and sandbox endpoints. [S6] Passport rotates refresh tokens by default (see the Handover section below), but that Connect runs Passport is an inference.
  - Handover's own Connect client saves the new `refresh_token` after every refresh (`UpdateMemberData`), so it assumes rotation. [S3]

  **Plan for rotation:** always save the `refresh_token` that comes back, and treat the old one as spent.

### Handover (handover.vatsim-scandinavia.org)

Handover is a Laravel app on Passport 13.7.5 / league/oauth2-server 9.4.1 (its `composer.lock`). [S2]

- **Issued: yes.** Passport's auth-code grant is set up with a refresh token TTL (`makeAuthCodeGrant()` → `setRefreshTokenTTL`), so every sign-in gets one. [S4]
- **Lifetimes:** in `AuthServiceProvider`, access tokens last 1 month (`Passport::tokensExpireIn(now()->addMonths(1))`) and refresh tokens 100 years (`Passport::refreshTokensExpireIn(now()->addYears(100))`). [S2]
- **Scopes:** all four Connect scopes plus `groups` are registered. The defaults are `full_name`, `email`, `vatsim_details` and `country`. A code comment notes that the scopes exist only to mimic Connect, though `UserCollection` does blank out fields whose scope the token lacks. [S2]
- **Rotation: yes.** `Passport::$revokeRefreshTokenAfterUse` defaults to `true` [S4] and Handover never changes it [S2]. league's `RefreshTokenGrant` revokes the old access token and the old refresh token, then issues new ones. [S5] A reused refresh token fails with `invalid_grant` ("Token has been revoked").
- **One token per app:** Handover's `RevokeOldTokens` listener revokes all of a user's other *access* tokens for the same client whenever a new one is issued. [S2] Refresh tokens aren't touched directly. In practice: a new sign-in or a refresh kills the previous access token, so store only the latest pair.

## 2. Does a refresh token keep working for someone who never signs in again?

- **Connect:** only within the documented 30 days. [S1] If refreshes rotate (unconfirmed, see above), refreshing more often than every 30 days should keep the chain going indefinitely. *Unconfirmed:* any absolute cap on a chain, whether a member can revoke an app's access from their VATSIM account, and what happens to tokens when a member is suspended or deleted. Treat a failed refresh (`invalid_grant`/400) as "this person has to sign in again" and fall back to the existing behaviour.
- **Connect `token_valid`:** `/api/user` returns `oauth.token_valid` ("Whether the access token is valid", `'true'`/`'false'`). [S1] Handover refuses a sign-in unless `token_valid === "true"`, with the error "Please grant us your full name, email address, VATSIM details, country and continious access…". [S3] That suggests Connect lets members decline "continuous access", which would make background use of their token impossible. *Unconfirmed:* the exact meaning of `token_valid`, and whether declining stops refresh tokens from being issued.
- **Handover:** effectively forever. Refresh tokens last 100 years and rotate on use. [S2][S4] Handover's daily `passport:purge` only deletes revoked tokens and tokens expired more than 7 days ago [S2], which a live chain never is. A refresh token stops working only if it's revoked or reused, or if Handover deletes the client or user.

## 3. Do VATSIM's terms allow background refreshes and server-side `/api/user` calls? Rate limits?

- **Nothing found that forbids it.** The Connect API docs present `refresh_token` as a grant "used by most clients". [S1] VATSIM Scandinavia's own Handover does exactly this: a daily scheduled `member:data` command refreshes each stored Connect token when it has expired and calls Connect's `/api/user` server-side to update rating, division, subdivision and so on. [S3]
- **No explicit permission found either.** The Connect docs (`vatsim.dev/services/connect`) only cover registration and the sandbox. [S1] The Data Protection and Handling Policy v1.4 says nothing about OAuth, third-party apps or polling. [S7] I couldn't extract the User Agreement v1.5 (the spec's `termsOfService` link) because its PDF text is font-encoded, so whether it says anything here is **unconfirmed**. [S8]
- **Rate limits: none documented, for Connect or for the Core API v2.** Neither OpenAPI spec mentions rate limits, throttling or 429 responses. [S1][S9] No rate-limit headers came back from `api.vatsim.net/v2/members/{cid}`, `auth.vatsim.net/api/user`, `auth-dev.vatsim.net/api/user` or Handover's `/api/user` (all behind Cloudflare except Handover). [S6] Actual limits are **unconfirmed**. Keep the existing pause between requests.
- **Compared with `v2/members`:** the Core API v2 `GET /v2/members/{member_id}` works without auth for everything except `name_first`/`name_last`, which need an API key. [S9] It also needs no per-user consent or token storage. Divisions and subdivisions can ask the tech team for Core API keys. [S10]
- **Webhooks (alternative):** VATSIM's webhooks push `member_changed_action` with field deltas (`rating`, `division_id`, `subdivision_id`, …) and are "the recommended way to subscribe to changes in the database, and is designed to supersede the old paradigm of polling the API for changes". There's no self-service configuration yet: "contact the tech team at tech@vatsim.net to request access". [S11]

## 4. Does `/api/user` pick up rating and subdivision changes promptly?

- **Connect: unconfirmed.** The docs don't say whether `/api/user` reads live member data or a copy taken when the token was issued. Handover's daily sync relies on it being current, since it calls `/api/user` every day for every user, even when the token hasn't expired. [S3] That suggests live data, but no source states it.
- **Handover: no, not live.** `OAuthUserController` returns `UserCollection` built from Handover's own `users` row. [S2] That row is updated only:
  - when the member signs in to Handover (`LoginController::completeLogin`) [S3], or
  - by the daily `member:data` command, which pulls `/api/user` from Connect using Handover's stored Connect tokens. If Handover's Connect refresh fails, it clears those tokens and stops updating that member, so their Handover data goes stale until they sign in to Handover again. [S3]

  So for Handover users, the data is up to one day old on Handover's side, plus however long our own refresh interval is.

## 5. Do the sandbox (auth-dev.vatsim.net) and Handover behave the same way?

- **Sandbox:** the same Connect OpenAPI spec lists `https://auth-dev.vatsim.net` as the "Sandbox" server, so the endpoints and grants are the same. [S1] The sandbox is "wholly independent of our production instance", its data "may be deleted at any time without notice", and its test accounts (CIDs 10000000–10000010) have fixed ratings and subdivisions. [S12] So rating changes can't be tested there, and the live `api.vatsim.net` doesn't know sandbox CIDs (as `SyncVatsimDetails` already notes). The sandbox token endpoint answers with the same error shape as production. [S6] Whether its token lifetimes match production is **unconfirmed**.
- **Handover vs Connect:** same endpoints (`/oauth/authorize`, `/oauth/token`, `/api/user`) and the same `/api/user` shape, with `oauth.token_valid` always `'true'` and `region`/`division`/`subdivision` names hard-coded to `'N/A'`. [S2] The differences that matter: access tokens last 1 month instead of about 7 days, refresh tokens 100 years instead of 30 days, rotation is confirmed, data is a daily copy, and every client skips the consent screen (`Client::skipsAuthorization` returns `true`). [S2]

## 6. How does Socialite 5.31 expose refresh tokens and refreshing?

All of this is in `vendor/laravel/socialite/src/Two/AbstractProvider.php` and `Token.php`, v5.31.0. [S13] Our `VatsimConnectProvider` extends `AbstractProvider` and overrides only the URLs, `getUserByToken` and `mapUserToObject`, so it inherits everything below. `HandoverProvider` only changes `$baseUrl`.

- **At sign-in:** `user()` → `userInstance()` sets `->token`, `->refreshToken`, `->expiresIn` and `->approvedScopes` on the returned `Laravel\Socialite\Two\User`, read from the token response's `access_token`, `refresh_token`, `expires_in` and `scope`. `SignInController` already gets this object from `Socialite::driver(...)->user()`. Today it just doesn't save the tokens.
- **Refreshing:** `refreshToken(string $refreshToken): Token` POSTs `grant_type=refresh_token`, `refresh_token`, `client_id` and `client_secret` to `getTokenUrl()` (our `{baseUrl}/oauth/token`). It returns `Laravel\Socialite\Two\Token` with public `token`, `refreshToken`, `expiresIn` and `approvedScopes`. No `scope` is sent, so the original scopes are kept (Connect: "optional"; league server: defaults to the old token's scopes). [S1][S5]
- **Fetching the user with a stored token:** `userFromToken(string $token)` calls our `getUserByToken()` (`GET {baseUrl}/api/user`) and `mapUserToObject()`. No session or state is needed, so it works from a console command or a queued job.
- **Gotchas:**
  - `Token::__construct(string $token, string $refreshToken, int $expiresIn, array $approvedScopes)` is strictly typed. If a refresh response leaves out `refresh_token` or `expires_in` (or the server returns an error body without throwing), building the `Token` throws a `TypeError`. Guzzle throws `ClientException` on a 4xx such as `invalid_grant`. Catch both.
  - `approvedScopes` comes from a `scope` string, but the Connect spec documents a `scopes` *array* in the token response [S1], and league's bearer response sends no scope at all. So `approvedScopes` will probably be `['']`. Don't rely on it.
  - `expiresIn` is in seconds, relative to the response. Store an absolute `expires_at`.
  - Driver config comes from `Socialite::buildProvider(..., config('services.vatsim'|'services.handover'))` in `AppServiceProvider`, so in a console context the same `Socialite::driver('vatsim')` / `driver('handover')` call gives a fully configured provider. `redirect` is unused for refreshing.

---

## Findings that affect the plan

- **Refresh tokens exist on both providers**, so the token-based approach is possible.
- **Connect's 30-day refresh-token life** means the job has to refresh every token at least that often, even when there's nothing to update. Daily is safe. Members whose refresh fails have to sign in again before they're updated, so the code needs a fallback (or keep `v2/members` as one).
- **Handover users can't be fresher than Handover's daily copy.** Moving Handover users from `v2/members` (live) to Handover's `/api/user` could make their data *older*, and puts us at the mercy of Handover's own Connect token chain. Calling `/api/user` more than daily gains nothing for them.
- **Rotation:** confirmed for Handover, assumed for Connect. Save the new refresh token every time, and serialize refreshes per user, because a reused token is revoked.
- **Unconfirmed:** whether VATSIM's User Agreement or other terms allow background use (nothing found against it, and VATSIM Scandinavia's Handover already does it), any rate limits, and whether Connect's `/api/user` returns live data.
- **Alternative to consider:** VATSIM webhooks (`member_changed_action`), the approach VATSIM recommends over polling. Currently by request to the tech team.

## Sources

- **[S1]** VATSIM Connect OpenAPI spec, `openapi/connect.yaml` in [vatsimnetwork/vatsim.dev](https://github.com/vatsimnetwork/vatsim.dev/blob/d99aaa9c4ac342954317357740865e77a137dfaf/openapi/connect.yaml) (rendered at <https://vatsim.dev/api/connect-api>), plus [`docs/services/connect/index.md`](https://github.com/vatsimnetwork/vatsim.dev/blob/d99aaa9c4ac342954317357740865e77a137dfaf/docs/services/connect/index.md). Commit `d99aaa9`, 2025-03-17.
- **[S2]** Handover source, [Vatsim-Scandinavia/handover](https://github.com/Vatsim-Scandinavia/handover/tree/77d80883cc0a1775d03c173cfd13dc3bf8ffd16e) at `77d8088`: `app/Providers/AuthServiceProvider.php`, `app/Listeners/RevokeOldTokens.php`, `app/Providers/EventServiceProvider.php`, `app/Models/Passport/Client.php`, `app/Http/Controllers/Api/OAuthUserController.php`, `app/Http/Resources/UserCollection.php`, `routes/api.php`, `app/Console/Kernel.php`, `composer.lock`.
- **[S3]** Handover's Connect client, same commit: `app/Console/Commands/UpdateMemberData.php`, `app/Helpers/ConnectHelper.php`, `app/Http/Controllers/Auth/LoginController.php`, `app/Http/Controllers/VatsimOAuthController.php`.
- **[S4]** Laravel Passport v13.7.5: [`src/Passport.php`](https://github.com/laravel/passport/blob/v13.7.5/src/Passport.php) (`$revokeRefreshTokenAfterUse = true`, `tokensExpireIn`, `refreshTokensExpireIn`) and [`src/PassportServiceProvider.php`](https://github.com/laravel/passport/blob/v13.7.5/src/PassportServiceProvider.php) (`makeAuthCodeGrant`, `makeRefreshTokenGrant`, `revokeRefreshTokens`), plus [`src/Bridge/RefreshTokenRepository.php`](https://github.com/laravel/passport/blob/v13.7.5/src/Bridge/RefreshTokenRepository.php).
- **[S5]** league/oauth2-server 9.4.1: [`src/Grant/RefreshTokenGrant.php`](https://github.com/thephpleague/oauth2-server/blob/9.4.1/src/Grant/RefreshTokenGrant.php) (`respondToAccessTokenRequest`, `validateOldRefreshToken`).
- **[S6]** Direct, unauthenticated probes on 2026-11-15: `GET https://api.vatsim.net/v2/members/1851903` (CID from VATSIM's webhook docs example), `GET /api/user` and `POST /oauth/token` (deliberately invalid client) against auth.vatsim.net, auth-dev.vatsim.net and handover.vatsim-scandinavia.org. Response headers and error bodies only.
- **[S7]** VATSIM Data Protection and Handling Policy v1.4, <https://cdn.vatsim.net/policy-documents/Data%20Protection%20and%20Handling%20Policy%20v1.4.pdf> (linked from <https://vatsim.net/docs/policy/data-protection-and-handling-policy>).
- **[S8]** VATSIM User Agreement v1.5, <https://cdn.vatsim.net/policy-documents/User%20Agreement%20v1.5.pdf>. Couldn't extract its text, so not reviewed.
- **[S9]** VATSIM Core API v2 OpenAPI spec, <https://api.vatsim.net/v2/openapi.json> (`GET /v2/members/{member_id}`).
- **[S10]** [`docs/services/apis.md`](https://github.com/vatsimnetwork/vatsim.dev/blob/d99aaa9c4ac342954317357740865e77a137dfaf/docs/services/apis.md) in vatsim.dev.
- **[S11]** [`docs/services/webhooks/`](https://github.com/vatsimnetwork/vatsim.dev/tree/d99aaa9c4ac342954317357740865e77a137dfaf/docs/services/webhooks) in vatsim.dev (`index.md`, `configuration.md`, `structure.md`).
- **[S12]** [`docs/services/connect/sandbox.md`](https://github.com/vatsimnetwork/vatsim.dev/blob/d99aaa9c4ac342954317357740865e77a137dfaf/docs/services/connect/sandbox.md) in vatsim.dev.
- **[S13]** laravel/socialite v5.31.0 as installed: `vendor/laravel/socialite/src/Two/AbstractProvider.php` (`user`, `userInstance`, `userFromToken`, `refreshToken`, `getRefreshTokenResponse`) and `vendor/laravel/socialite/src/Two/Token.php`.
