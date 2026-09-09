import assert from 'node:assert/strict';
import { test } from 'node:test';
import * as inertia from '@inertiajs/react';
import { createElement } from 'react';
import {
    eventTime,
    recurrenceLabel,
    updateEventStart,
} from '../../resources/js/lib/event-time.ts';
import {
    act,
    loadComponent,
    renderComponent,
} from './helpers/render-component.mjs';

const ui = {
    '@inertiajs/react': { ...inertia, Head: 'head', Link: 'link' },
    'lucide-react': {
        ArrowLeft: 'back-icon',
        Save: 'save-icon',
        Ban: 'ban-icon',
        CalendarDays: 'calendar-icon',
        ChevronRight: 'next-icon',
        Pencil: 'edit-icon',
        Plus: 'plus-icon',
        RotateCcw: 'restore-icon',
        X: 'remove-icon',
    },
    sonner: { toast: { success() {} } },
    '@/components/airport-picker': { AirportPicker: 'airport-picker' },
    '@/components/event-banner': { EventBanner: 'event-banner' },
    '@/components/event-field': { EventField: 'event-field' },
    '@/components/input-error': { __esModule: true, default: 'input-error' },
    '@/components/markdown-editor': { MarkdownEditor: 'markdown-editor' },
    '@/components/ui/alert': {
        Alert: 'alert',
        AlertDescription: 'alert-description',
        AlertTitle: 'alert-title',
    },
    '@/components/ui/badge': { Badge: 'badge' },
    '@/components/ui/button': { Button: 'button' },
    '@/components/ui/card': {
        Card: 'card',
        CardContent: 'card-content',
        CardDescription: 'card-description',
        CardHeader: 'card-header',
        CardTitle: 'card-title',
    },
    '@/components/ui/checkbox': { Checkbox: 'checkbox' },
    '@/components/ui/input': { Input: 'input' },
    '@/components/ui/label': { Label: 'label' },
    '@/components/ui/select': {
        Select: 'select',
        SelectContent: 'select-content',
        SelectGroup: 'select-group',
        SelectItem: 'option',
        SelectTrigger: 'select-trigger',
        SelectValue: 'select-value',
    },
    '@/components/ui/spinner': { Spinner: 'spinner' },
    '@/components/ui/textarea': { Textarea: 'textarea' },
    '@/components/ui/toggle-group': {
        ToggleGroup: 'toggle-group',
        ToggleGroupItem: 'toggle-group-item',
    },
    '@/components/ui/dialog': {
        Dialog: 'dialog',
        DialogContent: 'dialog-content',
        DialogDescription: 'dialog-description',
        DialogFooter: 'dialog-footer',
        DialogHeader: 'dialog-header',
        DialogTitle: 'dialog-title',
    },
    '@/lib/event-time': { eventTime, recurrenceLabel, updateEventStart },
    '@/routes/events': {
        index: () => ({ method: 'get', url: '/events' }),
        show: (id) => ({ method: 'get', url: `/events/${id}` }),
        edit: (id) => ({ method: 'get', url: `/events/${id}/edit` }),
        store: { url: () => '/events' },
        update: { url: (id) => `/events/${id}` },
    },
    '@/routes/events/roster': {
        show: ({ event, date }) => ({
            method: 'get',
            url: `/events/${event}/roster${date ? '/' + date : ''}`,
        }),
    },
    '@/routes/events/cancellations': { store: {}, destroy: {} },
    '@/routes/events/collaborations': { store: {}, destroy: {} },
};
const { default: EventForm } = loadComponent(
    new URL('../../resources/js/pages/events/form.tsx', import.meta.url),
    ui,
);
const { default: EventDetails } = loadComponent(
    new URL('../../resources/js/pages/events/show.tsx', import.meta.url),
    ui,
);
const fir = { id: 1, code: 'EKDK', name: 'Denmark' };
function eventData(overrides = {}) {
    return {
        id: 7,
        title: 'Copenhagen staffing',
        short_description: 'Evening staffing',
        short_description_html: '<p>Evening staffing</p>',
        description: '',
        status: 'draft',
        timezone: 'UTC',
        recurrence: 'none',
        recurrence_interval: 1,
        monthly_week: 2,
        recurrence_until: null,
        local_start: '2026-12-10T18:00',
        local_end: '2026-12-10T22:00',
        starts_at: '2026-12-10T18:00:00Z',
        ends_at: '2026-12-10T22:00:00Z',
        banner_url: null,
        owner: fir,
        owner_team_id: fir.id,
        airports: [],
        roster_enabled: false,
        roster_exists: false,
        roster_toggle_locked: false,
        schedule_locked: false,
        schedule_locked_by_cancellations: false,
        cancellation_reason: null,
        ...overrides,
    };
}
async function form(t, event) {
    const view = await renderComponent(
        createElement(EventForm, {
            event,
            firs: [fir],
            timezones: ['UTC', 'Europe/Copenhagen'],
        }),
    );
    t.after(() => view.unmount());
    return view;
}
function checkbox(view) {
    return view.find(
        (node) =>
            node.type === 'checkbox' && node.props.id === 'roster_enabled',
    );
}
function start(view) {
    return view.find(
        (node) => node.type === 'input' && node.props.id === 'local_start',
    );
}
function text(node) {
    return node.text ?? node.children.map(text).join('');
}
async function toggle(view, enabled) {
    await act(async () => checkbox(view).props.onCheckedChange(enabled));
}
async function submit(view) {
    await act(async () =>
        view
            .find((node) => node.type === 'form')
            .props.onSubmit({ preventDefault() {} }),
    );
}

await test('new events default to no roster and can enable it without locking the new schedule', async (t) => {
    const submissions = [];
    t.mock.method(inertia.router, 'post', (url, data) => {
        submissions.push({ url, data });
    });
    const view = await form(t, null);
    assert.equal(checkbox(view).props.checked, false);
    assert.equal(start(view).props.disabled, false);
    await submit(view);

    await toggle(view, true);
    await submit(view);

    assert.equal(start(view).props.disabled, false);
    assert.deepEqual(
        submissions.map(({ url, data }) => ({
            url,
            roster_enabled: data.roster_enabled,
        })),
        [
            { url: '/events', roster_enabled: false },
            { url: '/events', roster_enabled: true },
        ],
    );
});

await test('disabling an empty saved roster unlocks the schedule and submits the new time', async (t) => {
    let submitted;
    t.mock.method(inertia.router, 'post', (url, data) => {
        submitted = { url, data };
    });
    const view = await form(
        t,
        eventData({
            roster_enabled: true,
            roster_exists: true,
            schedule_locked: true,
        }),
    );
    assert.equal(start(view).props.disabled, true);

    await toggle(view, false);
    assert.equal(start(view).props.disabled, false);
    await act(async () =>
        start(view).props.onChange({ target: { value: '2026-12-10T19:00' } }),
    );
    await submit(view);

    assert.equal(submitted.url, '/events/7');
    assert.equal(submitted.data._method, 'put');
    assert.equal(submitted.data.roster_enabled, false);
    assert.equal(submitted.data.local_start, '2026-12-10T19:00');
    assert.match(
        text(view.find((node) => node.props.id === 'roster_enabled-hint')),
        /saved setup is kept/,
    );
});

await test('re-enabling a preserved roster locks the schedule again', async (t) => {
    const view = await form(t, eventData({ roster_exists: true }));
    assert.equal(checkbox(view).props.checked, false);
    assert.equal(start(view).props.disabled, false);

    await toggle(view, true);

    assert.equal(checkbox(view).props.checked, true);
    assert.equal(start(view).props.disabled, true);
});

await test('existing bookings or interest prevent disabling the roster', async (t) => {
    const view = await form(
        t,
        eventData({
            roster_enabled: true,
            roster_exists: true,
            roster_toggle_locked: true,
            schedule_locked: true,
        }),
    );

    assert.equal(checkbox(view).props.checked, true);
    assert.equal(checkbox(view).props.disabled, true);
    assert.equal(start(view).props.disabled, true);
    assert.match(
        text(view.find((node) => node.props.id === 'roster_enabled-hint')),
        /Withdraw all bookings and interest submissions/,
    );
});

await test('cancelled occurrences keep the schedule locked regardless of the roster toggle', async (t) => {
    const view = await form(
        t,
        eventData({
            roster_exists: true,
            schedule_locked: true,
            schedule_locked_by_cancellations: true,
        }),
    );
    assert.equal(start(view).props.disabled, true);
    await toggle(view, true);
    assert.equal(start(view).props.disabled, true);

    await toggle(view, false);

    assert.equal(start(view).props.disabled, true);
    assert.match(
        text(view.find((node) => node.type === 'alert-description')),
        /cancelled occurrences/,
    );
});

await test('a rejected roster toggle retains the selection and shows its validation error', async (t) => {
    t.mock.method(inertia.router, 'post', (url, data, options) => {
        options.onError({
            roster_enabled:
                'Withdraw all bookings and interest before disabling the roster.',
        });
    });
    const view = await form(
        t,
        eventData({ roster_enabled: true, roster_exists: true }),
    );
    await toggle(view, false);

    await submit(view);

    assert.equal(checkbox(view).props.checked, false);
    assert.equal(checkbox(view).props['aria-invalid'], true);
    assert.equal(
        view.find(
            (node) =>
                node.type === 'input-error' &&
                node.props.id === 'roster_enabled-error',
        ).props.message,
        'Withdraw all bookings and interest before disabling the roster.',
    );
});

for (const roster_enabled of [false, true]) {
    await test(`event details ${roster_enabled ? 'show' : 'hide'} roster links when the roster is ${roster_enabled ? 'enabled' : 'disabled'}`, async (t) => {
        const event = eventData({ roster_enabled, roster_exists: true });
        const view = await renderComponent(
            createElement(EventDetails, {
                event,
                description_html: '',
                occurrences: [
                    {
                        date: '2026-12-10',
                        starts_at: event.starts_at,
                        ends_at: event.ends_at,
                        status: 'scheduled',
                        reason: null,
                    },
                ],
                from: '2026-12-10',
                next_from: null,
                collaborations: [],
                firs: [],
                can: { edit: true, manage_owner: false },
            }),
        );
        t.after(() => view.unmount());

        const rosterLinks = view.findAll(
            (node) =>
                node.type === 'link' && node.props.href.url.includes('/roster'),
        );
        assert.deepEqual(
            rosterLinks.map((node) => node.props.href.url),
            roster_enabled
                ? ['/events/7/roster', '/events/7/roster/2026-12-10']
                : [],
        );
    });
}
