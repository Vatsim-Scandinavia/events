import type { InertiaFormProps } from '@inertiajs/react';
import type { RepeatEnds } from '@/components/events/schedule';
import type { RepeatInterval } from '@/lib/repeat';
import type { EventRoute } from '@/types/event';

export type RouteRow = EventRoute & { key: number };

// What the create and edit form holds. Dates and times are kept apart while editing and
// joined into starts_at and ends_at when sent.
export type EventFormData = {
    fir_id: number | '';
    name: string;
    event_type_id: number | '';
    description: string;
    airports: string;
    routes: RouteRow[];
    banner: File | null;
    remove_banner: boolean;
    booking_url: string;
    briefing_url: string;
    start_date: string;
    start_time: string;
    end_date: string;
    end_time: string;
    timezone: string;
    repeats: RepeatInterval;
    repeat_ends: RepeatEnds;
    repeat_count: number;
    repeat_until: string;
    // Fields Integrations add, by key. Empty keeps what's saved.
    integration_fields: Record<string, string>;
    remove_integration_fields: string[];
};

// A field an Integration adds to the form, such as a Discord webhook.
export type IntegrationField = {
    key: string;
    label: string;
    description: string | null;
    secret: boolean;
    // Never sent for secrets.
    value: string | null;
    // For a secret that is set, what it does, e.g. which channel it posts to.
    status: string | null;
};

export type EventForm = InertiaFormProps<EventFormData>;

// A field's error, including nested ones such as "routes.0.from".
export const errorFor = (form: EventForm, key: string) => (form.errors as Record<string, string | undefined>)[key];
