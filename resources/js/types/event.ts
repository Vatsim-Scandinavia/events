import type { RepeatInterval } from '@/lib/repeat';
import type { Fir } from '@/types/inertia';

// Mirrors App\Http\Resources\EventResource. Dates and times are preformatted in the
// viewer's time zone, UTC (zulu) unless they picked another.
export type HubEvent = {
    id: number;
    name: string;
    type: string;
    airports: string[];
    // The FIR that runs the event; null only for events from before FIRs existed.
    fir: Fir | null;
    startsAt: string;
    // Day of the month and short weekday, e.g. 18 and "Sat".
    day: number;
    weekday: string;
    date: string;
    month: string;
    time: string;
    banner: string | null;
    // How the event repeats in words, e.g. "Every week on Saturday", or null.
    repeats: string | null;
};

export type EventRoute = {
    from: string;
    to: string;
    route: string;
    // Only a label for pilots; routes saved before the flag existed don't have it.
    mandatory?: boolean;
};

// Mirrors App\Http\Resources\EventDetailResource.
export type HubEventDetail = HubEvent & {
    // HTML rendered from the staff's Markdown and sanitised on the server.
    description: string | null;
    endsAt: string;
    // Always in UTC, e.g. "16:00–19:30z", for viewers who picked another zone.
    zuluTime: string;
    duration: string;
    hasEnded: boolean;
    // The whole event is called off.
    cancelled: boolean;
    routes: EventRoute[];
    bookingUrl: string | null;
    briefingUrl: string | null;
};

// Mirrors the event prop of App\Http\Controllers\Staff\EventManagementController@edit.
export type EditableDate = {
    id: number;
    date: string;
    time: string;
    hasStarted: boolean;
    hasEnded: boolean;
    // Cancelled on its own; the whole event's cancellation is EditableEvent.cancelled.
    cancelled: boolean;
    bookings: number;
    interests: number;
    roster: number;
};

export type EditableEvent = {
    id: number;
    firId: number | null;
    name: string;
    typeId: number | null;
    // The zone startsAt, endsAt and the dates are given in, e.g. "Europe/Copenhagen".
    timezone: string;
    description: string | null;
    airports: string[];
    routes: EventRoute[];
    bannerUrl: string | null;
    bookingUrl: string | null;
    briefingUrl: string | null;
    startsAt: string;
    endsAt: string;
    repeats: RepeatInterval;
    repeatCount: number | null;
    repeatUntil: string | null;
    cancelled: boolean;
    dates: EditableDate[];
};
