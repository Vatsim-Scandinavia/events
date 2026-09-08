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
