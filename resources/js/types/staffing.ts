// Mirrors App\Staffing\StaffingPresenter. from/until are HH:MM in UTC, for editing;
// date, time and the *At labels are preformatted in the viewer's time zone.
export type StaffingMode = 'none' | 'open_interest' | 'shifts';

export type Controller = {
    id: number;
    name: string;
    cid: number | null;
    rating: string | null;
};

export type Occurrence = {
    id: number;
    date: string;
    time: string;
    hasStarted: boolean;
    hasEnded: boolean;
    // Cancelled on its own or with the whole event.
    cancelled: boolean;
};

// A labelled break in an Event's list of positions, grouping the positions below it.
export type PositionSection = {
    id: number;
    label: string | null;
};

export type StaffingPosition = {
    id: number;
    callsign: string;
    frequency: string | null;
    // Shared with Sections, which interleave with positions in the order staff set.
    sort: number;
    // The Section it falls under; null above the first one.
    section: PositionSection | null;
    // What the signed-in controller should know before working it, e.g. a missing endorsement.
    note: string | null;
};

// A known position from the Position catalogue, suggested when staff add a position.
export type CataloguePosition = {
    callsign: string;
    name: string | null;
    frequency: string | null;
};

export type Shift = {
    id: number;
    // e.g. "16:00–18:00z" or "18:00–20:00 CEST".
    time: string;
    // Minutes from the start of the event.
    startOffset: number;
    endOffset: number;
    booking: {
        id: number;
        controller: Controller;
        isMine: boolean;
        canCancel: boolean;
        // From Integrations, for staff and the controller booked; never a blocker.
        notes: string[];
    } | null;
};

export type PositionWithShifts = StaffingPosition & {
    shifts: Shift[];
};

export type AvailabilityWindow = {
    from: string;
    until: string;
    time: string;
    // Minutes from the start of the occurrence.
    fromOffset: number;
    untilOffset: number;
};

export type Interest = {
    id: number;
    positionIds: number[];
    callsigns: string[];
    // In time order, never overlapping.
    windows: AvailabilityWindow[];
    notes: string | null;
};

export type RosterEntry = {
    id: number;
    positionId: number;
    callsign: string;
    section: PositionSection | null;
    controller: Controller;
    time: string;
    // Minutes from the start of the occurrence.
    startOffset: number;
    endOffset: number;
    // From Integrations, for staff and the controller rostered; never a blocker.
    notes: string[];
};

export type InterestWindowState = 'upcoming' | 'open' | 'closed';

// Why the viewer can't book or show interest on the date, from App\Enums\BookingRefusal and
// App\Enums\InterestRefusal; null when they can.
export type BookingRefusal = 'draft' | 'not_shifts' | 'started' | 'cancelled' | 'not_next_date' | 'sign_in_needed' | 'not_controller';
export type InterestRefusal =
    'draft' | 'not_interest' | 'cancelled' | 'not_open_yet' | 'closed' | 'not_next_date' | 'sign_in_needed' | 'not_controller';

type StaffingBase = {
    // Controllers can't book or show interest until the event is published.
    draft: boolean;
    canManage: boolean;
    viewer: { signedIn: boolean; eligible: boolean };
    occurrence: Occurrence;
    occurrences: Occurrence[];
};

export type EventStaffing =
    | (StaffingBase & { mode: 'none' })
    | (StaffingBase & { mode: 'shifts'; booking: { refusal: BookingRefusal | null }; positions: PositionWithShifts[] })
    | (StaffingBase & {
          mode: 'open_interest';
          positions: StaffingPosition[];
          // `all` is everyone's interest, sent only to staff and vACC Staff.
          interest: {
              state: InterestWindowState;
              refusal: InterestRefusal | null;
              opensAt: string;
              closesAt: string;
              mine: Interest | null;
              all: StaffInterest[] | null;
          };
          rosterPublished: boolean;
          roster: RosterEntry[] | null;
      });

// The staff page's props, from StaffingPresenter::forStaff().
export type StaffOccurrence = Occurrence & {
    interestState: InterestWindowState;
    interestOpensAt: string;
    rosterPublished: boolean;
    rosterArchived: boolean;
    // The event is still a Draft, so its roster can't be published yet.
    eventDraft: boolean;
    startsAt: string;
    // What follows a time to name the viewer's zone, as DisplayTime writes it: "z" or " CEST".
    zone: string;
    durationMinutes: number;
};

export type StaffInterest = Interest & { controller: Controller };
