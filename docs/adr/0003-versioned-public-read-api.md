# The public API is versioned in its path, read-only, and v1 only grows

The vACC's website, Discord bots and other tools read the Hub's events through a JSON API under `/api/v1`. It is public, with no sign-in, and limited per address, because everything it serves is already on the public event pages: published Events, their Occurrences, positions and shifts, Shift Bookings and published Rosters, with controllers named by CID and name only. Drafts never appear; anything belonging to one is not found.

Within v1, changes only add things: new fields, endpoints and filters. Renaming or removing a field, or changing what one means, goes into a v2 served alongside v1, which keeps running for a stated deprecation period before it is retired. The API has its own resources, separate from the web app's, so changing a page never changes the API.

## Considered Options

- **Versioning by header** (`Accept: application/vnd…`). Rejected: harder to try in a browser, to link to and to read in logs, for nothing the path doesn't already give.
- **Reusing the web app's resources.** Rejected: they format times in the viewer's time zone and carry display text, and every UI change would become a breaking API change.
- **API tokens from the start.** Rejected: nothing served is private, and tokens would mean issuing and revoking keys for every consumer. Authentication arrives with the first endpoint that isn't public, in a later version.

## Consequences

A field added to v1 must be safe for existing consumers to ignore. Exposing more about controllers than the public pages show, or anything from a Draft, needs a new decision rather than a new field.
