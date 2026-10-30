# Discord is an Integration that posts through webhooks, and bookings happen only on the Hub

The legacy system took Shift Bookings through a Discord bot calling an API. The Hub takes them only on its own pages: there is no endpoint that books on a controller's behalf, and the public API stays read-only (ADR 0003). Discord becomes a place to see staffing, not to change it.

Discord is an Integration (ADR 0004) with two Integration features. **Event announcements** posts an Event announcement to one Hub-wide events channel the first time an Event is published; it replaces the announcement the core used to make itself. **Roster messages** keeps one Roster message in each Event's Roster channel: it shows the Roster of the next upcoming Occurrence that isn't cancelled, and is edited in place as staffing changes and, once that date ends, to show the next one. Both post through channel webhooks only, with no bot user: a webhook can post, edit and delete its own messages, which is all this needs, and the Hub keeps the ID of each message it posted. A Roster message never shows more than the public Event page does: nothing for a Draft, and for an Event staffed through Staffing Interest only a roster that has been published.

This needs two new extension points. **`EventPublished`** is announced by the core once a Draft is first published, so announcing an Event is no longer core code. **Event fields** let an Integration feature add fields to the Event form; the core renders them without knowing what they are, and the feature keeps the values in its own tables. Roster messages uses it for the Event's webhook, set by anyone who can manage the Event, since a Roster channel is a detail of the Event rather than Hub configuration.

## Considered Options

- **Keeping a bot booking API.** Rejected: it would need authentication that acts for a controller, and two places to book means two places to get the rules right.
- **A bot token that creates each Roster channel.** Rejected: it needs a hosted bot user with permission to manage channels, and rules for naming and archiving them. Staff already make a channel per Event; pasting its webhook is one step.
- **One forum channel with a post per Event**, through a single Hub-wide webhook. Rejected: it removes the manual step, but staff want real channels like today's.
- **A new message for every change, or for every date.** Rejected: it fills the channel. One message per Event, edited in place, never grows and pings nobody.
- **Leaving Event announcements in the core** and making only Roster messages an Integration. Rejected: the Hub should work fully with Discord switched off, and one Discord switch is easier to reason about than one Integration plus a stray environment variable.
- **Mapping Events to webhooks on the admin Integrations page.** Rejected: it's admin-only and away from where Events are set up.

## Consequences

A webhook URL is a secret: anyone holding it can post to the channel. It is stored encrypted, checked when saved, and never shown again; the form shows the channel it posts to. Because Event Staff paste per-Event webhooks, only Discord webhook URLs are accepted and redirects are never followed, so a webhook can't make the Hub call anywhere but Discord.

An Event announcement is best-effort, as the core's was: if Discord can't be reached when the Event is published, that's logged and it isn't retried. A Roster message has no such gap, because it's brought in line from current staffing every time.

Discord being down, slow or rate-limited never stops a booking, a roster change or publishing. Edits are queued per Event and coalesced, run one at a time per Event, and render staffing as it is when they run, so an older edit can never land after a newer one; a sweep catches anything missed, the same as Control Center's booking push. If the message has been deleted in Discord, the Hub posts a new one. If the webhook is replaced, the old message is deleted through the old webhook first, so the channel never shows two.

Controllers appear by name and CID in plain text and nobody is mentioned, because the Hub doesn't know anyone's Discord account. Linking accounts would be a decision of its own.

Installs that announced through `DISCORD_EVENTS_WEBHOOK` keep doing so: the variable now sets and locks the Event announcements webhook, and the upgrade switches that feature on where it's set.
