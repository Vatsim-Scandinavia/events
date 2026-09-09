---
paths:
  - 'app/**'
---

# App

## VATSIM identity owns the user account
The verified VATSIM CID is the user's primary key across all explicitly configured SSO providers. Never match or merge accounts by email or accept a CID from callback query/form input. Name, email, rating and organisational membership come from the provider; local password authentication is intentionally disabled. OAuth tokens on the user belong to the most recent sign-in provider and must stay encrypted and hidden from serialization.

## FIR authorization and event collaboration
Use Spatie Permission with five roles: Administrator and Pilot are global; Event Coordinator, vACC Staff (read only), and Controller are FIR-scoped. Users may belong to multiple FIRs; Pilot is the fallback when no roles are assigned. Controllers and Pilots currently have no explicit permissions. Each event has one owner FIR; accepted collaborator FIRs may edit that event, while only the owner FIR manages deletion and invitations. Manual role assignments are Administrator-only; external sync must preserve grants from other sources.

## Role grants and explicit FIR permission checks
Change roles through UpdateRoleAssignments, never Spatie's assignRole/syncRoles/removeRole or permission:assign-role: role_grants owns source provenance and model_has_roles is its transactional projection. Spatie pivot team_id=0 represents global grants; role definitions are shared with null team_id. Use Laravel can() gates with an explicit Team for FIR permissions; implicit Spatie Gate integration is disabled to prevent ambient team context bypass. Use effectiveRoleNames() for the computed Pilot fallback. External adapters must fetch a complete successful snapshot and use an allowlisted source/role mapping; an upstream failure must never be converted to an empty snapshot.

## FIR management reuses Team records
The FIR directory manages existing Team rows, with Administrator-only access through firs.manage. Renaming a FIR preserves its identity and role grants. Delete only unused FIRs; never cascade or rewrite manual/external role grants as part of FIR deletion.

## Audit application writes in their transaction
Record FIR CRUD, OAuth profile changes and role-assignment snapshots through RecordAudit inside the same database transaction as the write. Bulk role sync bypasses model events; compare deterministic snapshots including source provenance. Allowlist audited fields, never tokens or request payloads; ignore unchanged values. Audit history is Administrator-only and retains actor/subject snapshots without cascading foreign keys. Add audit calls when introducing new write paths; direct model/query-builder writes are not automatically audited.

## Event drafts, local schedules and airport entry
Events start as private drafts: only the owner FIR and accepted collaborator FIRs have access through events.view/events.manage; vACC Staff remain read-only. Save recurrence as local wall times plus an IANA timezone, use weekday-based intervals, and display UTC/Zulu by default. Preserve individual cancellation dates without shifting the series; prevent schedule changes once cancellations exist. Airport entry uses normalized ICAO codes with explicit details for unknown airports. Render Markdown with raw HTML stripped and unsafe links disabled; uploaded banners are private and authorized through the event.

## Event restoration preserves separate cancellations
Restoring an event or series returns it to a private draft and clears only event-level cancellation details; individually cancelled dates remain cancelled until restored separately. Restore the event before its individual occurrences. Restoration uses the same owner-versus-collaborator permissions as cancellation and is audited in the event write transaction.

## Preserve the final effective Administrator
UpdateRoleAssignments must reject any manual or external role update that removes the final effective Administrator, counting all grant sources after the complete change. Acquire the shared Administrator role write lock before user or grant locks, even inside callers' transactions; the unchanged write provides SQLite serialization where FOR UPDATE is ignored. Rejected changes must roll back grants, their role projection, and audits together.

## Event roster occurrences, eligibility and write serialization
Roster configuration belongs once to each event; local wall times and day offsets repeat through its timezone. Bookings and interest remain dated snapshots; default views advance without GET writes. Protect future commitments during template edits and retain historical snapshots. Event roster_enabled is coordinator-controlled: disabling requires all bookings and interest (including history) withdrawn, preserves the template and closes signups. Dates and recurrence may change while disabled unless occurrence cancellations exist; enabled templates lock schedules. Existing templates are backfilled enabled. Disabled rosters are excluded from directory/direct access and participation. Enabled opened rosters are visible to any Controller role in any FIR; management remains owner/accepted-collaborator scoped and participation requires an actual Controller role, including Administrators. Roster mutations target explicit occurrence_date where applicable; return_to_current only affects redirects. Serialize event edits and roster writes with the event write lock (also SQLite), with controller user lock before event for bookings; audit changes in the same transaction.
