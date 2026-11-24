# VATSIM Event Hub

An event hub for a single VATSIM vACC: pilots find events, controllers staff them, and staff plan them.

## People and sign-in

**Member**:
A person on the VATSIM network, known by their CID.
_Avoid_: VATSIM user, pilot (as a generic word for a person)

**Account**:
The Hub's own record of a Member, created the first time they sign in.
_Avoid_: Profile, user record

**Sign-in Provider**:
The service that vouches for a Member's CID when they sign in: VATSIM Connect or VATSCA Handover, one per Hub.
_Avoid_: Auth provider, OAuth server, SSO

**Member Details**:
What VATSIM says about a Member that the Hub keeps on their Account: name, first name, email, rating and subdivision.
_Avoid_: VATSIM details, profile data

## Roles

**Role**:
A named set of permissions that can be given to an Account, either Hub-wide or in a single FIR.
_Avoid_: Group, rank

**Hub-wide**:
Held across every FIR rather than in a single one. Whether a role applies Hub-wide or in a single FIR is decided each time it is given, not by the role itself. Administrator is always Hub-wide.
_Avoid_: Global

**Administrator**:
The built-in role with full rights, always Hub-wide, which also makes its holder a Controller everywhere.
_Avoid_: Admin, superuser

## Controllers

**Controller**:
An Account that may staff events, either Hub-wide or in a FIR. Being a Controller is not a role.
_Avoid_: Controller role, ATC user

**Controller Rule**:
The Hub's own test for being a Controller: rated S1 or above and in the vACC's subdivision. It applies Hub-wide.
_Avoid_: Automatic Controller role

**Controller Source**:
An Integration that decides who is a Controller instead of the Controller Rule. Only one decides at a time.
_Avoid_: Roster provider

**Controller Grant**:
A stored decision that an Account is a Controller in a FIR or Hub-wide, given either by the Controller Source or by hand.
_Avoid_: Endorsement, visiting role

## Events

**Event**:
Something a FIR runs on the network for Members to fly or control, taking place on one or more Occurrences.
_Avoid_: Session, booking

**Occurrence**:
One time an Event takes place, with its own start and end. An Event that repeats has one Occurrence for each time.
_Avoid_: Date, instance, session

**Draft**:
An Event that only staff of its FIR can see, until it is published. Publishing cannot be undone.
_Avoid_: Unpublished, hidden

**Cancelled**:
Called off without anything being deleted, either a whole Event or a single Occurrence. When an Event is cancelled, all of its Occurrences count as cancelled.
_Avoid_: Deleted, removed

**Reinstate**:
To undo a cancellation, possible until the Occurrence has started.
_Avoid_: Restore, uncancel
