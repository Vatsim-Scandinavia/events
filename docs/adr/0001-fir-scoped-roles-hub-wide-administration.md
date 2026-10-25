# Roles are held per FIR; hub configuration belongs to Administrator alone

Every role can be held in a single FIR or across the Hub, except Administrator, which is always hub-wide. Role definitions and the Hub's settings, FIRs and event types are shared by every FIR, so changing them is not a permission any other role can carry: only Administrators can, and only Administrators can give or take away Administrator.

## Considered Options

- **Per-FIR role definitions**, so each FIR could shape its own Event Staff. Rejected: a large redesign, and nothing asks for FIRs to differ in what a role allows.
- **Keeping "manage settings" and "manage roles" as permissions, but only counting them when held hub-wide.** Rejected: one more rule to remember for little gain, since anyone trusted with hub configuration is in practice an Administrator.

## Consequences

Administrator holds no permission other roles can't, so the rule that you can only grant roles allowing no more than you can do yourself doesn't protect it. Granting or removing Administrator is checked separately.
