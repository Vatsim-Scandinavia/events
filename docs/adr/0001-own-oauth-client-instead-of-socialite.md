---
status: superseded by ADR-0002
---

# Sign-in uses our own OAuth2 client, not Laravel Socialite

Members sign in through one Sign-in Provider per Hub, either VATSIM Connect or VATSCA Handover. Both speak the same OAuth2 authorization-code flow. Socialite only ever gave us the handshake (`AbstractProvider`), and everything around it was already ours: mapping Member Details, finding or creating the Account, and the failure handling. So we wrote a small client on Laravel's `Http` client instead. Two reasons. First, we keep and refresh tokens, and Socialite's refresh breaks when a provider doesn't send a new refresh token. Second, Socialite calls Guzzle directly, so `Http::fake` can't fake it, and tests would only cover a fake provider rather than our real token exchange.

## Considered Options

- **Keep Socialite with custom providers.** Rejected: it adds a package for about a hundred lines of protocol, leaves PKCE off by default, has no typed failures, and can't be tested at the HTTP boundary.
- **thephpleague/oauth2-client.** Rejected: also built on Guzzle, and it leaves storing and checking state and PKCE to the caller anyway.

## Consequences

Security rules a library would otherwise carry are now ours to keep: S256 PKCE plus single-use state, a fixed redirect URI, encrypted tokens kept out of the session, refreshes locked per Account, and no retries of the code exchange.
