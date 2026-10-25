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

export type StaffingPosition = {
    id: number;
    callsign: string;
    frequency: string | null;
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
    controller: Controller;
    time: string;
    // Minutes from the start of the occurrence.
    startOffset: number;
    endOffset: number;
    // From Integrations, for staff and the controller rostered; never a blocker.
    notes: string[];
};

export type InterestWindowState = 'upcoming' | 'open' | 'closed';

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
    | (StaffingBase & { mode: 'shifts'; bookingOpen: boolean; positions: PositionWithShifts[] })
    | (StaffingBase & {
          mode: 'open_interest';
          positions: StaffingPosition[];
          // `all` is everyone's interest, sent only to staff and vACC Staff.
          interest: { state: InterestWindowState; opensAt: string; closesAt: string; mine: Interest | null; all: StaffInterest[] | null };
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
    durationMinutes: number;
};

export type StaffInterest = Interest & { controller: Controller };
