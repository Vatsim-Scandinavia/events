export type Fir = { id: number; code: string; name: string };
export type Airport = {
    id: number;
    icao: string;
    name: string;
    country: string;
};
export type Pagination<T> = {
    data: T[];
    current_page: number;
    last_page: number;
    total: number;
};
export type EventSummary = {
    id: number;
    title: string;
    short_description: string;
    short_description_html: string;
    status: 'draft' | 'cancelled';
    timezone: string;
    recurrence: 'none' | 'weekly' | 'monthly';
    starts_at: string;
    ends_at: string;
    banner_url: string | null;
    owner: Fir;
    airports: Airport[];
};
export type ManagedEvent = EventSummary & {
    owner_team_id: number;
    description: string;
    local_start: string;
    local_end: string;
    recurrence_interval: number;
    monthly_week: number | null;
    recurrence_until: string | null;
    cancellation_reason: string | null;
    schedule_locked: boolean;
};
export type Occurrence = {
    date: string;
    starts_at: string | null;
    ends_at: string | null;
    status: 'scheduled' | 'cancelled' | 'skipped';
    reason: string | null;
};
