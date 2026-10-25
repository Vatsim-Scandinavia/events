# VATSIM Event Hub

An event hub for a single VATSIM vACC: pilots find events, controllers staff them, and staff plan them.

## Organisation

**Hub**:
The whole vACC as this app sees it, spanning all of its FIRs.
_Avoid_: Site, instance

**Hub-wide**:
Applying across the whole Hub rather than in a single FIR. Every role can be held per FIR except Administrator, which is always hub-wide.
_Avoid_: Global

**Theme**:
The colors the whole Hub is shown in, chosen by an Administrator, either Default or VATSIM Scandinavia.
_Avoid_: Skin, palette

## Events

**Event**:
Something the vACC organises on the network, run by one FIR, taking place on one or more Occurrences.
_Avoid_: Calendar entry

**Draft**:
An Event not yet visible to pilots and controllers; only staff who can view or manage events see it.
_Avoid_: Unpublished, hidden

**Publish**:
To make a Draft Event visible to everyone and announce it on Discord. Rosters are also published, per Occurrence.
_Avoid_: Release, go live

**Occurrence**:
One date on which an Event takes place. Shown to people as a "date".
_Avoid_: Instance, session

**Slot link**:
An Event's link to an external system where pilots reserve arrival or departure slots.
_Avoid_: Booking link, slot booking link

## Staffing

**Shift Booking**:
A controller claiming a pre-defined shift on a position for an Occurrence. "Booking" on its own always means this.
_Avoid_: Reservation, slot

**Staffing Interest**:
A controller's statement that they want to work an Occurrence, with the positions they'd like.
_Avoid_: Sign-up, application

**Availability**:
The time windows within a Staffing Interest when the controller can work.
_Avoid_: Interest window

**Roster**:
Who works which position, and when, on an Occurrence: its shifts, booked or open, for an Event staffed through shifts, which are always public; or its Roster Entries for an Event staffed through Staffing Interest, once published.
_Avoid_: Line-up, schedule

**Roster Entry**:
A controller placed on a position for a span of time in an Occurrence's roster.
_Avoid_: Roster slot, slot

## Roles

**Administrator**:
The built-in role that can do everything, always held hub-wide.
_Avoid_: Admin, superuser

**Event Staff**:
The built-in role that plans events and their staffing.
_Avoid_: Event team, organiser

**vACC Staff**:
The built-in role for vACC staff outside the events team, who can see every Event and its staffing but not change them.
_Avoid_: Observer, read-only staff

**Controller**:
The built-in role that may book shifts and show Staffing Interest; given automatically by the Controller source, or granted by hand.
_Avoid_: ATC, rated member

**Controller source**:
Whatever currently decides who automatically holds the Controller role, and where: either the hub's own rule (rated S1 or above in the vACC's subdivision, across the Hub) or one Integration feature. Only one is in charge at a time.
_Avoid_: Roster provider, role sync

**Pilot**:
What someone with no role is; not a role itself.
_Avoid_: Guest, member

## Sign-in

**Sign-in provider**:
Where people sign in and get their VATSIM CID vouched for: VATSIM Connect, or VATSIM Scandinavia's Handover, which stands in for it. One per install, chosen when it's deployed. Handover the system has nothing to do with automatic roles being handed over to admins when a Controller source is switched off.
_Avoid_: Auth provider, SSO, login method

## Integrations

**Integration**:
A connection to one external system, such as the vACC's training system, that Administrators can switch on for the whole Hub. The Hub works fully with every Integration switched off.
_Avoid_: Plugin, module, connector

**Integration feature**:
One job an Integration can do, switched on separately, such as syncing the controller roster, syncing positions or pushing bookings.
_Avoid_: Capability, sub-integration

**Position catalogue**:
The Hub-wide list of known controller positions that staff pick from when adding positions to an Event.
_Avoid_: Position list, position registry
