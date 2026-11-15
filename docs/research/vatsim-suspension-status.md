# Does VATSIM expose a member's suspension or ban status?

Research for [#242](https://github.com/Vatsim-Scandinavia/events/issues/242) (part of map [#238](https://github.com/Vatsim-Scandinavia/events/issues/238)). Sources checked on 2026-11-15.

## Answer

Yes. A suspension shows up as a **controller rating of `0` (`SUS`)**. Both places the Hub already reads carry it, and neither needs a new scope or credential:

- **At sign-in (VATSIM Connect `/api/user`):** `data.vatsim.rating.id` / `.short` under the `vatsim_details` scope, which the Hub already requests.
- **From the members API (`GET https://api.vatsim.net/v2/members/{cid}`):** `rating`, plus `susp_date`, both returned without an API key.

**Bug found in the Hub:** `app/Enums/ControllerRating.php` has the two values the wrong way round. It maps `-1` to `Suspended`/`SUS` and `0` to `Inactive`/`INA`. VATSIM defines **`-1` = `INA`/`INAC` Inactive** and **`0` = `SUS` Suspended**. Any suspension check built on the current enum would test for the wrong value.

**Whether Connect refuses suspended members on its own:** no first-party source says it does. VATSIM's own sandbox has a suspended test account, which suggests a suspended member can still finish a Connect sign-in. That is an inference, not a documented fact, so the Hub should check the rating itself and not rely on Connect.

## Findings

### 1. Rating IDs: `0` is suspended, `-1` is inactive

| ID | VATSIM.dev ratings page | Data feed v3 `ratings` | Hub `ControllerRating` enum |
|---|---|---|---|
| -1 | `INA`, Inactive | `INAC`, Inactive | `Suspended`, label `SUS` (wrong) |
| 0 | `SUS`, Suspended | `SUS`, Suspended | `Inactive`, label `INA` (wrong) |
| 1 | `OBS`, Pilot/Observer | `OBS`, Observer | `OBS` |

- Source: https://vatsim.dev/resources/ratings (the Controller table lists `-1 | INA | Inactive` and `0 | SUS | Suspended`).
- Source: https://data.vatsim.net/v3/vatsim-data.json, `ratings` array: `{"id": -1, "short": "INAC", "long": "Inactive"}, {"id": 0, "short": "SUS", "long": "Suspended"}`.
- Hub: `app/Enums/ControllerRating.php` has `case Suspended = -1; case Inactive = 0;`. This has been there since `236ee70 feat: initial codebase`.

The two VATSIM sources disagree on the short code for `-1` (`INA` or `INAC`). They agree on what each ID means.

"Inactive" and "suspended" are different states. Inactive means the account has lapsed and the member has to reactivate it. Suspended is a disciplinary action. Only `0` means suspended. VATSIM documents no separate "ban" field or value. A permanent ban looks like a suspension with no end date (see `susp_date` below), but that part is inference.

Effect today: because both values are below `S1`, `ControllerRole` (`app/Auth/ControllerRole.php:48`, `rating >= S1`) already denies the automatic Controller role to both states. The swap only shows up in the labels shown in the UI (Profile, Admin users, Staffing, the shared Inertia prop) and in any future check for "suspended".

### 2. VATSIM Connect, at sign-in

- Scopes are `full_name`, `email`, `vatsim_details`, `country`. `vatsim_details` is "Read the user's VATSIM ratings and divisions". Source: https://vatsim.dev/api/connect-api/vatsim-connect-api.
- `GET /api/user` returns `data.vatsim.rating` as `{ id, short, long }` ("Controller Rating ID", "Short identifier", "Human-readable name"), and `pilotrating` in the same shape. There is no dedicated suspended, banned or active field. The only status-like field is `oauth.token_valid`. Source: https://vatsim.dev/api/connect-api/get-user.
- The Hub already reads `vatsim.rating.id` in `app/Http/Controllers/SignInController.php` (`ControllerRating::tryFrom(...)`). So it already *receives* the suspension signal at every sign-in. It just mislabels it and doesn't act on it.
- **Freshness:** the record is read when `/api/user` is called during the callback, so it is as current as VATSIM's member record at that moment. VATSIM does not document any caching on this endpoint.

### 3. Does Connect refuse suspended members?

- The Connect docs for authorize, token and user list only OAuth errors (`invalid_request`, `invalid_client`, `invalid_grant`, `unauthorized_client`, `unsupported_grant_type`) and the user endpoint's `VSO1`/`VSO2`/`VSO3` "Invalid client" codes. None is described as an account-status refusal. Sources: https://vatsim.dev/api/connect-api/redirect, https://vatsim.dev/api/connect-api/get-token, https://vatsim.dev/api/connect-api/get-user.
- The Connect sandbox's test accounts include **`10000000` "Zero Web", controller rating "Suspended"**, with the same login scheme as the other accounts. Source: https://vatsim.dev/services/connect/sandbox. A suspended account that can sign in is good evidence that Connect hands suspended members to the client, and leaves the decision to the client. This is an inference, not a statement from VATSIM.
- Community reports of "account is suspended" errors are about connecting to the *network* (the FSD/pilot and ATC clients), not about Connect sign-in. Example: https://forum.vatsim.net/t/vatsim-account-is-suspended/2500. This is low-trust and only used for background.
- **Cheap way to confirm:** sign in to a local install against `auth-dev.vatsim.net` as sandbox account `10000000`. If the callback arrives, Connect does not refuse suspended members, and you can see the exact `rating` payload (`id: 0`, `short: "SUS"` expected).

### 4. Members API (`api.vatsim.net/v2/members/{cid}`)

From VATSIM's OpenAPI spec (https://api.vatsim.net/v2/openapi.json, rendered at https://vatsim.dev/api/core-api/members-api-retrieve-member-details):

- The response is `Member` (with an API key) or `LimitedMemberSchema` (without one). **Both include `rating` (required) and `susp_date` (date-time, optional).** The fields restricted to authenticated callers are `name_first` and `name_last`. So the key-less call `users:sync-vatsim` already makes (`app/Console/Commands/SyncVatsimDetails.php`) gets both fields.
- `susp_date` is not described in the spec. The superuser write schema `MemberUnrestricted` ("this can set suspended ratings and passwords") also has `susp_prevrat`. That suggests how it works: during a suspension, `rating` is overwritten with `0` and the previous rating is kept in `susp_prevrat`, which is write-side only and not returned. `susp_date` is most likely the date the suspension ends, but VATSIM does not document this.
- **Freshness:** this is a live read of the member record. `users:sync-vatsim` runs daily, so it would catch a suspension made between sign-ins within about a day. When the suspension ends, `rating` presumably returns to the previous rating, and the next sync or sign-in would pick that up.
- There is no separate "bans" endpoint in API v2. The member paths are `/flightplans`, `/stats`, `/status` (online connection), `/atc`, `/history`, `/online` and `/discord/{id}`.

## Implications for #238

- Fix the `ControllerRating` enum (`Suspended = 0`, `Inactive = -1`) before anything relies on "suspended". It is a one-line swap, plus a test that `0` maps to `SUS`.
- A "refuse suspended members at sign-in" rule can use `vatsim.rating.id === 0` from Connect. It needs no new scope.
- A "sign out / block members suspended since they last signed in" rule can use `rating === 0` from the existing daily members-API sync. It needs no API key. `susp_date` could be shown as "suspended until".
- Whether to also treat Inactive (`-1`) as blocked is a policy question. VATSIM treats it as a lapsed account, not a sanction.
