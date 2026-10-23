# VATSIM Event Hub

An event hub for a single VATSIM vACC: pilots find events, controllers staff them, and staff plan them.

## Organisation

**Hub**:
The whole vACC as this app sees it, spanning all of its FIRs.
_Avoid_: Site, instance

**Hub-wide**:
Applying across the whole Hub rather than in a single FIR. Every role can be held per FIR except Administrator, which is always hub-wide.
_Avoid_: Global

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
The built-in role that may book shifts and show Staffing Interest; given automatically to members rated S1 or above in the vACC's subdivision, or granted by hand.
_Avoid_: ATC, rated member

**Pilot**:
What someone with no role is; not a role itself.
_Avoid_: Guest, member
