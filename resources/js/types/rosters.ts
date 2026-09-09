import type { Occurrence } from '@/types/events';

export type RosterMode = 'pre_slotted' | 'open_interest';
export type RosterAvailability = { starts_at: string; ends_at: string };
export type RosterController = { cid: number; name: string };
export type RosterSlot = RosterAvailability & {
    id: number;
    callsign: string;
    booking: RosterController | null;
    can_book: boolean;
};
export type RosterInterest = {
    id: number;
    user: RosterController;
    position_ids: number[];
    availability: RosterAvailability[];
};
export type EventRoster = {
    id: number;
    mode: RosterMode;
    is_open: boolean;
    shifts: { id: number; name: string; slots: RosterSlot[] }[];
    positions: { id: number; callsign: string }[];
    interests: RosterInterest[];
};
export type RosterPageProps = {
    event: { id: number; title: string; timezone: string };
    occurrence: Occurrence & { has_ended: boolean };
    roster: EventRoster | null;
    canManage: boolean;
    canParticipate: boolean;
    canViewEvent: boolean;
    currentUserCid: number;
};
