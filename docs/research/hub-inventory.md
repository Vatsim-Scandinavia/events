# Hub inventory: where each concept lives today

Fact-finding for [Inventory the Hub's concepts and where each lives today](https://github.com/Vatsim-Scandinavia/events/issues/221), part of the map [Settle how the Hub is built: a Core, Modules and Integrations](https://github.com/Vatsim-Scandinavia/events/issues/220).

This file records what the code does now (`dev` at `0b1b2c3`). It proposes no Modules and no seams. Where it says "area", it means a group of code as the code itself groups it today, not a Module in the glossary's sense. Terms follow `GLOSSARY.md`.

Size: about 11,000 lines of PHP in `app/` and `routes/`, and about 9,000 lines of TS/TSX in `resources/js`.

---

## 1. How the code is grouped today

Two layouts sit side by side:

- **Laravel's type folders.** These hold everything: `app/Models` (18), `app/Http/Controllers` (top level, `Admin/`, `Staff/`, `Api/V1/`), `app/Http/Requests` (4), `app/Http/Resources` (page and `Api/V1`), `app/Enums` (14), `app/Policies` (1: `EventPolicy`), `app/Console/Commands` (4).
- **Folders named after an area.** These are `app/Staffing` (9 classes), `app/Events` (3: `EventPublished`, `EventField(s)`), `app/Auth` (6), `app/Integrations` (10, plus `ControlCenter/` 11 and `Discord/` 9). There's also `app/Services` (10), a grab-bag of unrelated things.

The area folders hold only part of each area. For example, Staffing's models, enums (`BookingRefusal`, `InterestRefusal`, `RosterState`, `StaffingMode`), controllers (`ShiftBookingController`, `StaffingInterestController`, `Staff/StaffingController`, `Staff/RosterController`) and frontend types live elsewhere.

The frontend is grouped partly by area (`components/events/`, `components/staffing/`, `components/staffing/planner/`) and partly not. `event-card.tsx`, `timezone-select.tsx`, `network-stats.tsx`, `logo-input.tsx`, `integration-run-badge.tsx` and `admin-header.tsx` sit at the root of `components/`, next to the shared `components/ui/`. Pages are grouped by URL (`pages/Events`, `pages/Admin/*`, `pages/Profile`). Types are grouped by area in `types/` (`event.ts`, `staffing.ts`, `admin.ts`, `inertia.d.ts`).

---

## 2. Concepts, where they live, and their front doors

"Front door" means the place the rest of the code is meant to go through, if there is one. "Reached past by" lists callers that go round it.

### Events (Event, Occurrence, Draft, Publish, Summary, Slot link, cancellation, URLs)

| Concept | Lives in | Front door today | Reached past by |
|---|---|---|---|
| Event record, slug URLs, Draft/Publish, cancel/restore, remove | `Models/Event.php` (401 lines): rich model with `publish()`, `cancel()`, `restore()`, `remove()`, scopes `published`, `upcoming`, `visibleTo` | The model itself | Everyone uses the model directly |
| Occurrence, its cancel/restore | `Models/EventOccurrence.php` | The model | — |
| The "current" Occurrence (next one that isn't cancelled) | `Event::currentOccurrence()` | Yes, one method | Used in 9 places, including both refusal checks and both `EventResource`s |
| Dates from a repeat rule, moving/removing dates | `Staffing/EventSchedule.php` (252), with `Enums/RepeatInterval.php`. It's about Event dates but lives in the `Staffing` namespace | `EventSchedule` | Called from `EventRequest::plan()` and `EventManagementController` |
| Repeat rules on the client | `resources/js/lib/repeat.ts`: copies `RepeatInterval::fits()` and `EventSchedule::MAX_DATES` by hand (commented as such) | — | — |
| Event form input | `Http/Requests/EventRequest.php` (148), `EventScheduleRequest.php`. `EventRequest` also reads `Integrations` for Event fields | FormRequest | — |
| Event form props | Inline array in `Staff/EventManagementController::edit()`, about 40 keys | — | — |
| Event list/detail props | `Http/Resources/EventResource.php`, `EventDetailResource.php` | Resource | — |
| Event in the public API | `Http/Resources/Api/V1/EventResource.php`, `EventDetailResource.php`, `OccurrenceResource.php`, `OccurrenceDetailResource.php`. These share class names with the page resources | Resource | — |
| Banner image | `Services/EventBanners.php`, called from `Event::booted()` (model event), both `EventResource`s, `LinkPreview`, `EventAnnouncements`, `EventManagementController` | `EventBanners` | — |
| Description Markdown → HTML | `Services/EventDescription.php` (static `toHtml`), called by the page and API detail resources | Yes | — |
| Link previews | `Services/LinkPreview.php`, called by `EventController::show` | Yes | — |
| Event type | `Models/EventType.php` (`FALLBACK` constant), `Admin/EventTypeController` | — | — |
| Events list filter | `EventController::index()` (inline query, FIR/airport parsing) | — | — |
| `EventPublished` announcement | `app/Events/EventPublished.php`, dispatched from `Event::publish()` | — | — |
| Event fields extension point | `app/Events/EventField(s).php` and `Integrations::eventFields()`. Saved by `EventManagementController::saveIntegrationFields()` | Partly: the controller does the looping | — |

### Staffing (positions, Sections, shifts, Shift Booking, Staffing Interest, Availability, Roster, Roster Entry)

| Concept | Lives in | Front door today | Reached past by |
|---|---|---|---|
| Positions, Sections | `Models/EventPosition.php`, `EventSection.php`. Their relations and `sectionsFor()` are on `Models/Event.php` | `Event::sectionsFor()` | — |
| Shift times on a date | `PositionShift::startsAtFor()/endsAtFor()` | Yes | — |
| Booking a shift, cancelling, why someone can't (`BookingRefusal`) | `Staffing/ShiftBookings.php` | `ShiftBookings` | — |
| Showing interest, withdrawing, why someone can't (`InterestRefusal`) | `Staffing/StaffingInterests.php`. The interest window is on `EventOccurrence` (`interestOpensAt`, `interestClosesAt`, `interestWindowState()` returning the strings `'upcoming'`/`'open'`/`'closed'`) | `StaffingInterests` | — |
| Saving the staffing planner (positions, Sections, shifts, roster) in one go | `Staffing/StaffingPlan.php` (320) | `StaffingPlan::save()` | — |
| Switching staffing mode | `Event::switchStaffingMode()`, on the Event model, not in `app/Staffing` | — | — |
| Publishing a roster | `EventOccurrence::publishRoster()/unpublishRoster()` | — | — |
| The Roster as someone may see it | `Staffing/Roster.php`, `RosterItem.php`, `Enums/RosterState.php`. The class doc says every place that shows a Roster reads it from here | `Roster::of()` | **`StaffingPresenter::forStaff()`** builds the roster and the shifts with its own private `roster()` and `positionsWithBookings()`. The sort is the same, but it doesn't go through `Roster::of()`. `forEventPage()` does use `Roster::of()` |
| Staffing page props | `Staffing/StaffingPresenter.php` (385): `forEventPage()` and `forStaff()` | Yes | — |
| Roster in the public API | `Api/V1/RosterController` → `Roster::of()` → `RosterItemResource`. The API re-sorts by start then callsign | Yes | — |
| `StaffingChanged` announcement | `Staffing/StaffingChanged.php`. Dispatched from `Event` (5 places), `EventOccurrence` (4), `EventSchedule`, `ShiftBookings` (2), `StaffingPlan` | — | — |
| `StaffingNotes` extension point | `Staffing/StaffingNotes.php` (interface). Collected in `StaffingPresenter::notes()`/`positionNote()` through `Integrations::staffingNotes()` | — | — |
| Position catalogue | `Models/CataloguePosition.php`. Read inline in `Staff/StaffingController::edit()`, written by `ControlCenter/PositionSync` | — | — |

### Access (users, roles, permissions, Controller role, sign-in, Profile, Name display)

| Concept | Lives in | Front door today | Reached past by |
|---|---|---|---|
| Permissions | `Enums/Permission.php`. Every case is a Gate (`AppServiceProvider::boot()`), plus `Gate::before` for Administrators and an `administer` Gate | Gates | — |
| Checking a permission | `User::hasPermission()`, `hasPermissionFor()`, `permissionNames()`, `firIdsWithAny()`, `managesUsersIn()`, `isAdministrator()` (on the model), `Auth/Roles.php` (static: `within`, `canGrant`, `grantableIn`, labels), and `EventPolicy` | Several, see §4.2 | — |
| Who counts as a Controller for an Event | `User::isEligibleController()` (instance) and `User::controllers()` (query scope). The rule is written twice, with a comment saying they're the same | — | — |
| Built-in roles | `Auth/Roles.php` (static constants and `sync()`) | `Roles` | — |
| Controller source and the hub's own rule | `Auth/ControllerRole.php` (instance), `Auth/ControllerSource.php` (interface). Whether the role is automatic is stored through `Services/VaccIdentity::controllerRoleIsAutomatic()` | `ControllerRole` | — |
| Sign-in | `Http/Controllers/SignInController.php`, `Auth/VatsimConnectProvider.php`, `HandoverProvider.php`, `Enums/SignInProvider.php`, `Auth/DevLogin.php` (static) | — | — |
| VATSIM member details | `Console/Commands/SyncVatsimDetails.php` (its own HTTP call) | — | — |
| Name display | `User::displayName()`, `showsCidBesideName()`, `displayNameWithCid()`, `Enums/NameDisplay.php` | `User` methods | **`Discord/RosterMessageContent`** rebuilds `displayNameWithCid()` inline: `displayName() . (showsCidBesideName() ? " ({$cid})" : '')` |
| The shape of a "controller" for pages | `StaffingPresenter::controller()` → `{id, name, cid, showCid, rating}` | — | — |
| The shape of a "controller" for the API | `Api/V1/ControllerResource` → `{cid, name}` | — | — |
| The signed-in user for pages | Inline closure in `HandleInertiaRequests::share()` | — | — |
| Display time zone | `User::displayTimezone()`, shared as `displayTimezone` | — | — |

### Hub settings (vACC identity, Theme, logo, FIRs)

| Concept | Lives in | Front door today |
|---|---|---|
| vACC identity, Theme, logo, favicon, cached FIR list, Controller-role-automatic flag | `Services/VaccIdentity.php`. Its cache is cleared by `Models/Fir::booted()` | `VaccIdentity` |
| Logo files | `Services/SiteLogo.php`. It and `EventBanners` both build on `Services/GdImages.php` | `SiteLogo` |
| Settings form | `SiteSettingsRequest` (reads `VaccIdentity::locked()`), `Admin/SiteSettingsController` | — |
| FIRs | `Models/Fir.php`, `Admin/FirController` | — |

### Integrations framework, Control Center, Discord

| Concept | Lives in | Front door today |
|---|---|---|
| Registry, configuration, switching on and off, conflicts, scheduling | `Integrations/Integrations.php` (289). It also hands out each extension point: `controllerSource()`, `staffingNotes()`, `eventFields()` | `Integrations` |
| Contract | `Integration.php`, `Feature.php`, `Syncable.php`, `Setting.php`, `Configuration.php`, `SyncContext/SyncResult`, `RunSync` (job), `Models/IntegrationRun`, `IntegrationSetting` | — |
| Admin page | `Admin/IntegrationController.php` (289, inline validation and props) | — |
| Control Center | `ControlCenterApi` (HTTP), `RosterSync` (Controller source and `StaffingNotes`), `PositionSync`, `BookingPush` (listens to `StaffingChanged`), `PushBookings` (job), `PushedBooking`, `Member`, `AreaMap`, `Coverage` | — |
| Discord | `Webhook` (HTTP client: no redirects, keeps the URL out of errors), `EventAnnouncements` (listens to `EventPublished`), `RosterMessages` (listens to `StaffingChanged`, Event fields), `UpdateRosterMessage`/`DeleteRosterMessage` (jobs), `RosterMessageContent` (reads `Roster::of()`) | — |

### Other

- **Public site:** `HomeController`, `Services/VatsimNetworkStats.php` (its own HTTP call and cache), `components/network-stats.tsx`.
- **Time:** `Services/DisplayTime.php` (how times are shown, per viewer), `Services/ZuluTime.php` (static: reading HH:MM against a date), `resources/js/lib/time.ts` (clock times in the browser).
- **Errors and layout:** `AppServiceProvider` (Inertia exception handling), `pages/Error.tsx`, `layouts/AppLayout.tsx`.

---

## 3. The same thing done in more than one place

Each item is a rule, shape or behaviour written more than once. The files are the evidence.

1. **Callsign and frequency rules** appear four times. `Staffing/StaffingPlan.php` (`CALLSIGN`, `FREQUENCY`), `Integrations/ControlCenter/PositionSync.php` (the same two constants, byte for byte), `resources/js/components/staffing/planner/draft.ts` (`CALLSIGN`) and `planner/add-position-row.tsx` (the frequency regex inline).
2. **ICAO code rules** come in two variants. `/^[A-Z0-9]{4}$/` is in `EventController::index()` and three times in `EventRequest` (airports, route from, route to). `/^[A-Z]{4}$/` is in `Admin/FirController`. The two differ on digits.
3. **The Roster's order and contents** are built twice. `Staffing/Roster::shifts()/entries()` and `StaffingPresenter::positionsWithBookings()/roster()` each load positions, call `sectionsFor`, key bookings by shift and sort entries by position order then start. The API then re-sorts `Roster::of()` its own way in `Api/V1/RosterController`.
4. **Who may see a Draft** is written twice: as a query in `Event::visibleTo()` and per record in `EventPolicy::view()`. The API controllers use a third form, `abort_if($event->isDraft(), 404)` (three times), and `EventController::show` uses `abort_unless(Gate::allows('view'))`.
5. **Who counts as a Controller for an Event** is written twice: `User::isEligibleController()` and the query scope `User::controllers()`.
6. **The Administrator short-circuit** is in `Gate::before`, again inside `User::hasPermission()` and again in `User::permissionNames()`.
7. **Whether an Occurrence is cancelled** is `EventOccurrence::isCancelled()` (its own or the Event's). `Api/V1/OccurrenceResource` works it out again (`cancelled_at ?? event->cancelled_at`). `EventManagementController::edit()` reads `cancelled_at` on purpose to mean "cancelled on its own".
8. **"Only the next date can be staffed"** is the same `currentOccurrence()?->isNot($occurrence)` line in `ShiftBookings::refusal()` and `StaffingInterests::refusal()`, alongside `EventController::show()`'s comment saying controllers only staff the next date.
9. **The interest window message** is worded twice in `StaffingInterests`: in `refuse()` and again in `ensureWindowIsOpen()`, which redoes the window check on the `'upcoming'`/`'closed'` strings rather than the refusal.
10. **Refusal wording** is on both sides. The server words every `BookingRefusal`/`InterestRefusal` in `ShiftBookings::refuse()`/`StaffingInterests::refuse()`, and the client words them again, differently, in `components/staffing/shift-board.tsx` and `open-interest.tsx` (for example, `not_next_date`: "Shifts can only be booked for the next date of this event." against "Shifts open for this date once the one before it is over.").
11. **Name display with CID** is done by `User::displayNameWithCid()`, by hand in `Discord/RosterMessageContent`, and on the client in `components/staffing/controller-name.tsx` (`controllerText`).
12. **A "controller" as data** has two shapes: `StaffingPresenter::controller()` (pages) and `Api/V1/ControllerResource` (API). The signed-in user is a third shape, in `HandleInertiaRequests`.
13. **Minutes from an Occurrence's start** are worked out inline about ten times with `(int) $occurrence->starts_at->diffInMinutes(...)`: in `StaffingPresenter` (five), `StaffingPlan`, `EventSchedule` (three) and `Event::durationInMinutes()`. `PositionShift` keeps its own offsets.
14. **The FIR list** is queried as `Fir::query()->orderBy('icao')->get([...])` in `EventController`, `EventManagementController::firs()`, `Admin/UserController` (twice) and, cached, in `VaccIdentity`.
15. **Outbound HTTP** is set up five different ways. `Discord/Webhook` (no redirects, 10s, keeps the secret out of errors), `Discord/EventAnnouncements` (calls `Http::` directly, 5s, scrubs the secret by hand: the same Integration bypasses its own `Webhook` class), `ControlCenter/ControlCenterApi`, `VatsimNetworkStats` (5s) and `SyncVatsimDetails` (10s).
16. **"Is this feature on?"** is the same `app(Integrations::class)->isOn(app(<Integration>::class), $this)` boilerplate in each feature (`EventAnnouncements`, `RosterMessages`, `BookingPush`, …).
17. **Server enums mirrored by hand in TS:** `StaffingMode`, `BookingRefusal`, `InterestRefusal`, `InterestWindowState` (`types/staffing.ts`), `PermissionName` (`types/inertia.d.ts`), `IntegrationRunStatus` (`types/admin.ts`), `RepeatInterval` (`lib/repeat.ts`).
18. **Repeat rules on both sides:** `RepeatInterval::fits()`/`describe()` and `EventSchedule::MAX_DATES`, copied in `lib/repeat.ts`.
19. **Image handling:** `EventBanners` and `SiteLogo` each scale and store an upload on top of `GdImages`, each with its own private resize helper.

---

## 4. The same kind of job done in different ways

These aren't one rule copied; they're one kind of job with no settled way of doing it.

### 4.1 Where domain logic lives
- **On the model:** `Event::publish/cancel/restore/remove/switchStaffingMode`, `EventOccurrence::cancel/restore/publishRoster/unpublishRoster`, `User::assignRole` and the permission maths.
- **In a class beside the model:** `ShiftBookings`, `StaffingInterests`, `StaffingPlan`, `EventSchedule`, `Roster`, `ControllerRole`.
- **In the controller:** the events list filter (`EventController::index`), Event field saving and banner clean-up (`EventManagementController`), user search (`Admin/UserController`, including a Postgres special case).
- **In model events:** `Event::booted()` creates the first Occurrence and deletes the banner. `Fir::booted()` clears `VaccIdentity`'s cache.

### 4.2 Authorization
Five mechanisms are in use, often several on one request:
- route middleware (`can:manage-events`, `can:manage-staffing`, `can:administer`, `can:manage-users`)
- `Gate::authorize`/`Gate::allows` in controllers (Staff controllers, `EventController`)
- `EventPolicy` (Events and Staffing abilities)
- domain classes throwing `AuthorizationException` (`ShiftBookings`, `StaffingInterests`; `ShiftBookingController` has no checks of its own)
- `abort_if`/`abort_unless` 404s for Drafts (API controllers, `EventController::show`)

### 4.3 Validation
- **FormRequests:** 4 (`EventRequest`, `EventScheduleRequest`, `SiteSettingsRequest`, `Api/V1/EventFilterRequest`). `EventRequest` also builds domain values (`plan()`, `details()`, `repeatRule()`, `firstSpan()`).
- **Inline `$request->validate()`:** 9 controllers (`ProfileController`, `StaffingInterestController`, `Staff/StaffingController` twice, and every Admin controller except `SiteSettingsController` and `UserController`).
- **Domain checks throwing `ValidationException`:** `Event`, `EventOccurrence`, `ShiftBookings`, `StaffingInterests`, `StaffingPlan` (which also re-checks callsign and frequency formats), `EventSchedule`. These are HTTP-shaped exceptions raised from models and services.

### 4.4 Shaping data for pages and the API
- **Page Resources:** `Http/Resources/EventResource`, `EventDetailResource` (called with `->resolve()`).
- **API Resources:** `Http/Resources/Api/V1/*`, with the same class names as the page ones.
- **Presenter:** `StaffingPresenter` (arrays, `array<string, mixed>`).
- **Inline arrays in controllers:** `EventManagementController::edit` (about 40 keys), `ProfileController`, `IntegrationController`, `RoleController`, `UserController`, `DevLoginController`, `HandleInertiaRequests`.
- **Times:** pre-formatted strings from `DisplayTime` (most places), ISO strings for the browser to work with (`startsAt`, the planner), and minute offsets (the planner, the API).
- **TS types:** written by hand in `resources/js/types/*.ts` and in per-page `Props` types (for example `EventsShowProps` in `pages/Events/Show.tsx`). Nothing ties them to the PHP shapes. `usePage()` takes a page-specific type argument in 1 of 15 uses; the other 14 rely on the shared props typed globally in `types/inertia.d.ts`.

### 4.5 Getting a service
- **Constructor or method injection:** controllers, `StaffingPresenter`, `ControllerRole`.
- **`app(...)`/service locator:** `EventResource`, the API `EventResource`, `EventRequest`, `SiteSettingsRequest`, `HandleInertiaRequests`, `Event::booted`, most Integration features.
- **Static classes:** `Roles`, `ZuluTime`, `DevLogin`, `GdImages`, `EventDescription`, `RosterMessageContent::for()`.
- **`DisplayTime` is obtained three ways:** `app(DisplayTime::class)` (bound to the signed-in user), `DisplayTime::for($user)`, and `new DisplayTime(...)` for zulu or an Event's zone.

### 4.6 Announcements
`StaffingChanged` is dispatched from models, from classes in `app/Staffing`, and from `EventSchedule` (which is in `app/Staffing` but is about Event dates). `EventPublished` is in `app/Events`, and `StaffingChanged` is in `app/Staffing`. Listeners are only Integration features, registered from `Feature::boot()` with `Event::listen` closures. The core never listens (by ADR 0004).

### 4.7 User-facing copy and labels
- **Copy on the server:** enum `label()`s (`Permission`, `NameDisplay`, `Theme`, `ControllerRating`, `SignInProvider`), `RepeatInterval::describe()`, refusal and validation messages, flash messages.
- **Copy on the client:** refusal hints (`shift-board.tsx`, `open-interest.tsx`), repeat labels (`lib/repeat.ts`), time zone descriptions (`lib/time.ts`).
- **Intent sent rather than words:** Name display (`showCid`, from #211) and refusal codes (from #207) are sent as intent, but the server words the same refusals for its own errors.

### 4.8 Long-running and queued work
- `RunSync` (`WithoutOverlapping` per feature)
- `PushBookings` and `UpdateRosterMessage` (`ShouldBeUniqueUntilProcessing` and `WithoutOverlapping` per Occurrence or Event, each with its own timeouts)
- hourly sweeps registered through `Feature::schedule()`
- the `ExtendRepeatingEvents` and `SyncRoles` console commands

### 4.9 Caching
`VaccIdentity` (forever, with keys cleared by `Fir` model events) and `VatsimNetworkStats` (`Cache::flexible`). Nothing else caches.

---

## 5. Frontend

| Area | Pages | Components | Types / lib |
|---|---|---|---|
| Events | `Events/Index`, `Show` (404), `Form` | `event-card.tsx` (root), `components/events/*` (12 files, form parts and cancellation) | `types/event.ts`, `lib/repeat.ts`, `lib/description.ts` |
| Staffing | `Events/Staffing` | `components/staffing/*` (event page parts: `shift-board`, `open-interest` (368), `sections`, `staffing-notes`, `controller-name`, `sign-in-prompt`), `staffing/timeline.tsx` (687), `use-timeline-drag.ts`, `staffing/planner/*` (8) | `types/staffing.ts` (152) |
| Access | `Profile/Edit`, `Auth/DevLogin`, `Admin/Users/*`, `Admin/Roles/*` | — | `types/admin.ts`, `types/inertia.d.ts` |
| Hub settings | `Admin/Settings/Edit`, `Admin/Firs/*`, `Admin/EventTypes/*` | `logo-input.tsx`, `timezone-select.tsx` (root) | `types/inertia.d.ts` (`Vacc`, `Fir`) |
| Integrations | `Admin/Integrations/Index`, `Edit` (343) | `integration-run-badge.tsx` (root), `events/integration-fields-card.tsx` | `types/admin.ts` |
| Public site | `Home` | `network-stats.tsx` (root) | — |
| Shared | `Error`, `layouts/AppLayout` | `components/ui/*` (shadcn-style, 17), `admin-header`, `confirm-dialog`, `detail-list`, `section-header` | `lib/utils.ts`, `lib/time.ts` |

Facts about how pages talk to the server:
- **Forms:** `useForm` in 10 files.
- **Visits:** `router.post/put/delete/visit` in 15 files, including inside components (`shift-board`, `open-interest`, `roster-card`, `cancellation`).
- **Plain `fetch`:** once, in `components/events/schedule.tsx`, for the schedule preview. That's the only standalone request (`useHttp` isn't used).
- **The timeline:** `staffing/timeline.tsx` is one 687-line component, with only `MoveRowButton` and `Block` split out. `pages/Events/Show.tsx` holds the page plus six helper components and a `useNow` hook.

---

## 6. Tests

- **`tests/Feature`:** mostly flat, one file per behaviour (`EventDraftTest`, `ShiftBookingTest` under `Staffing/`, `ControlCenterRosterSyncTest`, …). There are subfolders for `Admin/` (10), `Api/` (3) and `Staffing/` (7). Integration tests sit at the top level (`ControlCenter*Test`, `Discord*Test`), not in a folder.
- **`tests/Unit`:** `DisplayTimeTest`, `ZuluTimeTest` only.
- **`tests/Fakes`:** `FakeIntegration`, `OtherIntegration`, `FakeRosterSync`, `FakeBookingPush`. These are test adapters for the Integration contract.
- **Frontend:** no tests.

---

## 7. Facts later tickets will need

These aren't proposals, just facts gathered on the way.

- `app/Events` holds `EventPublished` and `EventField(s)`, but Laravel also reads "Events" as event classes in general. The Event Module's name and Laravel's folder name collide.
- `EventSchedule` (Event dates) lives in `App\Staffing`.
- `VaccIdentity` stores the Controller-role-automatic flag, which belongs to Access.
- Each extension point has its own method on `Integrations` (`controllerSource()`, `staffingNotes()`, `eventFields()`). Adding one means editing the registry.
- Nothing stops one area's code reaching into another's models. Every area uses `Event`, `EventOccurrence` and `User` directly, including Integrations (`RosterSync` queries `User` and `Member`; `BookingPush` reads `Roster::of()`).
- Per ADR 0004, the core never listens to its own announcements. So today announcements are only a seam for Integrations, never between areas of the core.
