# Integrations are built in, switched on by Administrators, and never required

The Hub talks to the vACC's other systems (its training system's controller roster and positions, network booking services) through Integrations. Each Integration is one external system, built into this repo against a shared contract and registered in one list. Administrators switch it on for the whole Hub, and switch each of its Integration features on separately. Settings follow the vACC identity pattern: chosen in the app, locked when set in the environment; secrets come from the environment or are stored encrypted, and an Integration can't be switched on until a connection test passes.

The Hub works fully with every Integration switched off. An Integration only adds to the core, or takes over a job through an extension point the core offers; the first is the Controller source, the single thing that decides who automatically holds the Controller role. With an Integration's roster sync in charge, the hub's own S1-and-subdivision rule steps aside, and the Integration grants the Controller role per FIR (ADR 0001) from where the training system says a member is active or holds a visiting endorsement.

What an Integration syncs in, it owns: its records are read-only in the Hub, overwritten on the next sync. Automatic Controller roles belong to whichever Controller source is in charge, whoever gave them, so a new source's first sync replaces what the previous one gave; the removal limit holds that run for an Administrator's approval. Manual grants live alongside and are never touched. A sync that fails, partly or wholly, changes nothing; one that would remove more than a set share of its grants, or that no longer sees an area mapped to a FIR, is held until an Administrator approves it. Switching a Controller source off hands the automatic roles to admins as manual ones, so nobody loses access without someone deciding so, and the hub's own rule takes over again. Integrations are Hub configuration, so only Administrators manage them (ADR 0001).

## Considered Options

- **Integrations as installable Composer packages.** Rejected for now: no other vACC has asked to ship its own, and package discovery is machinery we'd have to keep stable. The contract is kept clean enough to open this up later.
- **One switch per feature, with the external system picked inside it.** Rejected: credentials, rate limits, outages and health belong to the external system, so that is where they live; features within it are still switched separately.
- **Letting several sources grant the Controller role at once.** Rejected: when two sources disagree about removing someone, neither can tell whether it may. One source in charge keeps removal unambiguous.
- **Removing every grant an Integration made when it's switched off.** Rejected: it would lock controllers out in the middle of a season because an admin flipped a switch.

## Consequences

Every Integration must be safe to switch off at any moment, and no core code may check whether one is on outside the extension points. A new extension point (something an Integration may take over) needs its own decision, not just a new feature.

Mapping the training system's areas to FIRs depends on what that system uses to name them. Control Center names areas, not codes, so renaming an area there breaks the mapping, which is why a mapped area going missing holds the sync rather than emptying that FIR's roster.
