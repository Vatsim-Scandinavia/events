import type { Occurrence } from '@/types/events';

export type RosterMode = 'pre_slotted' | 'open_interest';
export type RosterAvailability = { starts_at: string; ends_at: string };
export type RosterController = { cid: number; name: string };
export type RosterSlot = {
    starts_at: string | null;
    ends_at: string | null;
    is_unavailable: boolean;
    id: number;
    callsign: string;
    booking: (RosterController & { id: number }) | null;
    can_book: boolean;
    is_locked: boolean;
};
export type RosterInterest = {
    id: number;
    user: RosterController;
    position_ids: number[];
    position_callsigns: string[];
    availability: RosterAvailability[];
};
export type RosterBooking = RosterAvailability & {
    id: number;
    slot_id: number | null;
    callsign: string;
    shift_name: string;
    can_withdraw: boolean;
    user: RosterController;
};
export type EventRoster = {
    id: number;
    mode: RosterMode;
    is_open: boolean;
    mode_locked: boolean;
    shifts: { id: number; name: string; slots: RosterSlot[] }[];
    positions: { id: number; callsign: string; is_locked: boolean }[];
    interests: RosterInterest[];
    bookings: RosterBooking[];
};
export type RosterPageProps = {
    event: { id: number; title: string; timezone: string };
    occurrence: Occurrence & { has_ended: boolean };
    roster: EventRoster | null;
    canManage: boolean;
    canParticipate: boolean;
    canViewEvent: boolean;
    currentUserCid: number;
    nextOccurrenceDate: string | null;
    autoSelectOccurrence: boolean;
    occurrenceOptions: Occurrence[];
};
