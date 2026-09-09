import assert from 'node:assert/strict';
import { test } from 'node:test';
import * as inertia from '@inertiajs/react';
import { createElement } from 'react';
import { eventTime } from '../../resources/js/lib/event-time.ts';
import {
    act,
    loadComponent,
    renderComponent,
} from './helpers/render-component.mjs';

const ui = {
    '@inertiajs/react': { ...inertia, Head: 'head', Link: 'link' },
    'lucide-react': {
        Plus: 'plus-icon',
        Save: 'save-icon',
        X: 'remove-icon',
        ArrowLeft: 'back-icon',
        Pencil: 'edit-icon',
        ChevronRight: 'next-icon',
        ChevronLeft: 'previous-icon',
        CalendarDays: 'calendar-icon',
        ClipboardList: 'clipboard-icon',
    },
    sonner: { toast: { success() {} } },
    '@/components/alert-error': { __esModule: true, default: 'alert-error' },
    '@/components/input-error': { __esModule: true, default: 'input-error' },
    '@/components/event-field': { EventField: 'event-field' },
    '@/components/ui/button': { Button: 'button' },
    '@/components/ui/input': { Input: 'input' },
    '@/components/ui/checkbox': { Checkbox: 'checkbox' },
    '@/components/ui/label': { Label: 'label' },
    '@/components/ui/spinner': { Spinner: 'spinner' },
    '@/components/ui/badge': { Badge: 'badge' },
    '@/components/ui/alert': {
        Alert: 'alert',
        AlertTitle: 'alert-title',
        AlertDescription: 'alert-description',
    },
    '@/components/ui/card': {
        Card: 'card',
        CardContent: 'card-content',
        CardDescription: 'card-description',
        CardHeader: 'card-header',
        CardTitle: 'card-title',
        CardFooter: 'card-footer',
    },
    '@/components/ui/select': {
        Select: 'select',
        SelectContent: 'select-content',
        SelectGroup: 'select-group',
        SelectItem: 'option',
        SelectTrigger: 'select-trigger',
        SelectValue: 'select-value',
    },
    '@/components/ui/toggle-group': {
        ToggleGroup: 'toggle-group',
        ToggleGroupItem: 'toggle-group-item',
    },
};
const routes = {
    '@/routes/events/roster': {
        update: (event) => ({ method: 'put', url: `/events/${event}/roster` }),
        show: ({ event, date }) => ({
            method: 'get',
            url: `/events/${event}/roster${date ? '/' + date : ''}`,
        }),
    },
    '@/routes/roster/interest': {
        update: (id) => ({ method: 'put', url: `/rosters/${id}/interest` }),
        destroy: (id) => ({ method: 'delete', url: `/rosters/${id}/interest` }),
    },
    '@/routes/roster/bookings': {
        withdraw: ({ roster, booking }) => ({
            method: 'delete',
            url: `/rosters/${roster}/bookings/${booking}`,
        }),
        store: ({ roster, slot }) => ({
            method: 'post',
            url: `/rosters/${roster}/slots/${slot}/booking`,
        }),
        destroy: ({ roster, slot }) => ({
            method: 'delete',
            url: `/rosters/${roster}/slots/${slot}/booking`,
        }),
    },
    '@/routes/events': {
        show: (id) => ({ method: 'get', url: `/events/${id}` }),
    },
    '@/routes/rosters': { index: () => ({ method: 'get', url: '/rosters' }) },
};
const { RosterTimeFields } = loadComponent(
    new URL(
        '../../resources/js/components/roster-time-fields.tsx',
        import.meta.url,
    ),
    ui,
);
const { RosterEditor } = loadComponent(
    new URL('../../resources/js/components/roster-editor.tsx', import.meta.url),
    {
        ...ui,
        ...routes,
        '@/components/roster-time-fields': { RosterTimeFields },
    },
);
const { RosterInterestForm } = loadComponent(
    new URL(
        '../../resources/js/components/roster-interest-form.tsx',
        import.meta.url,
    ),
    {
        ...ui,
        ...routes,
        '@/components/roster-time-fields': { RosterTimeFields },
    },
);
const { default: RosterPage } = loadComponent(
    new URL('../../resources/js/pages/events/roster.tsx', import.meta.url),
    {
        ...ui,
        ...routes,
        '@/components/roster-editor': { RosterEditor: 'roster-editor' },
        '@/components/roster-interest-form': {
            RosterInterestForm,
        },
        '@/lib/event-time': { eventTime },
    },
);
const event = {
    id: 7,
    title: 'Copenhagen staffing',
    timezone: 'Europe/Copenhagen',
};
const occurrence = {
    date: '2026-12-10',
    starts_at: '2026-12-10T18:00:00.000Z',
    ends_at: '2026-12-10T22:00:00.000Z',
    status: 'scheduled',
    reason: null,
};
const controller = { cid: 1000001, name: 'Chris Controller' };
function emptyRoster(overrides = {}) {
    return {
        id: 9,
        mode: 'pre_slotted',
        is_open: false,
        mode_locked:
            overrides.shifts?.some((shift) =>
                shift.slots.some((slot) => slot.is_locked),
            ) || !!overrides.interests?.length,
        bookings: [],
        shifts: [],
        positions: [],
        interests: [],
        ...overrides,
    };
}
function slot(overrides = {}) {
    return {
        id: 11,
        callsign: 'EKCH_A_TWR',
        starts_at: '2026-12-10T18:00',
        ends_at: '2026-12-10T20:00',
        booking: null,
        can_book: true,
        is_locked: !!overrides.booking,
        is_unavailable: false,
        ...overrides,
    };
}
function text(node) {
    return node.text ?? node.children.map(text).join('');
}
function button(view, label) {
    return view.find((node) => node.type === 'button' && text(node) === label);
}
async function mount(t, component, props) {
    const view = await renderComponent(
        createElement(component, {
            occurrenceOptions: props.occurrence ? [props.occurrence] : [],
            nextOccurrenceDate: null,
            autoSelectOccurrence: false,
            ...props,
        }),
    );
    t.after(() => view.unmount());
    return view;
}
async function change(view, id, value) {
    await act(async () =>
        view
            .find((node) => node.type === 'input' && node.props.id === id)
            .props.onChange({ target: { value } }),
    );
}

await test('the roster editor submits shift identities and UTC times and preserves rejected input', async (t) => {
    let submitted;
    t.mock.method(inertia.router, 'put', (url, data, options) => {
        submitted = { url, data };
        options.onError({
            'shifts.0.slots.0.starts_at':
                'This position overlaps the late shift.',
        });
    });
    const view = await mount(t, RosterEditor, {
        event,
        occurrence,
        roster: emptyRoster({
            shifts: [{ id: 3, name: 'Early', slots: [slot()] }],
        }),
        onClose: assert.fail,
    });
    await change(view, 'slot-11-callsign', 'ekch_b_twr');
    await change(view, 'slot-11-starts_at', '2026-12-10T19:55');

    await act(async () =>
        view
            .find((node) => node.type === 'form')
            .props.onSubmit({ preventDefault() {} }),
    );

    assert.deepEqual(submitted, {
        url: '/events/7/roster',
        data: {
            mode: 'pre_slotted',
            is_open: false,
            occurrence_date: '2026-12-10',
            shifts: [
                {
                    id: 3,
                    name: 'Early',
                    slots: [
                        {
                            id: 11,
                            callsign: 'EKCH_B_TWR',
                            starts_at: '2026-12-10T19:55',
                            ends_at: '2026-12-10T20:00',
                        },
                    ],
                },
            ],
            positions: [],
        },
    });
    assert.equal(
        view.find(
            (node) =>
                node.type === 'input' && node.props.id === 'slot-11-starts_at',
        ).props.value,
        '2026-12-10T19:55',
    );
    assert.equal(
        view.find(
            (node) =>
                node.type === 'event-field' &&
                node.props.id === 'slot-11-starts_at',
        ).props.error,
        'This position overlaps the late shift.',
    );
});

await test('booked positions cannot be edited or removed and their shift and roster type stay protected', async (t) => {
    const view = await mount(t, RosterEditor, {
        event,
        occurrence,
        roster: emptyRoster({
            shifts: [
                {
                    id: 3,
                    name: 'Early',
                    slots: [slot({ booking: controller })],
                },
            ],
        }),
        onClose() {},
    });

    assert.equal(
        view.find(
            (node) =>
                node.type === 'input' && node.props.id === 'slot-11-callsign',
        ).props.disabled,
        true,
    );
    assert.equal(
        view.find(
            (node) =>
                node.type === 'input' && node.props.id === 'slot-11-starts_at',
        ).props.disabled,
        true,
    );
    assert.equal(
        view.find(
            (node) =>
                node.type === 'button' &&
                node.props['aria-label'] === 'Remove position EKCH_A_TWR',
        ).props.disabled,
        true,
    );
    assert.equal(button(view, 'Remove shift').props.disabled, true);
    assert.equal(
        view.findAll((node) => node.type === 'fieldset')[0].props.disabled,
        true,
    );
});

await test('adding positions to a new shift keeps the other shifts and their inputs', async (t) => {
    const view = await mount(t, RosterEditor, {
        event,
        occurrence,
        roster: null,
        onClose() {},
    });
    await act(async () => button(view, 'Add shift').props.onClick());
    await change(view, 'new-shift-0-name', 'Early');
    await act(async () => button(view, 'Add position').props.onClick());
    await change(view, 'new-slot-1-callsign', 'ekch_a_twr');
    await act(async () => button(view, 'Add shift').props.onClick());

    assert.equal(
        view.find(
            (node) =>
                node.type === 'input' && node.props.id === 'new-shift-0-name',
        ).props.value,
        'Early',
    );
    assert.equal(
        view.find(
            (node) =>
                node.type === 'input' &&
                node.props.id === 'new-slot-1-callsign',
        ).props.value,
        'EKCH_A_TWR',
    );
    assert.equal(
        view.find(
            (node) =>
                node.type === 'input' &&
                node.props.id === 'new-slot-1-starts_at',
        ).props.value,
        '2026-12-10T18:00',
    );
});

await test('interest submits selected positions and multiple availability windows without editor keys', async (t) => {
    let submitted;
    t.mock.method(inertia.router, 'put', (url, data) => {
        submitted = { url, data };
    });
    const view = await mount(t, RosterInterestForm, {
        occurrence,
        roster: emptyRoster({
            mode: 'open_interest',
            is_open: true,
            positions: [
                { id: 2, callsign: 'EKCH_A_TWR' },
                { id: 4, callsign: 'EKCH_APP' },
            ],
        }),
        interest: undefined,
        canSubmit: true,
    });
    await act(async () =>
        view
            .find(
                (node) =>
                    node.type === 'checkbox' &&
                    node.props.id === 'interest-position-4',
            )
            .props.onCheckedChange(true),
    );
    await change(view, 'availability-0-ends_at', '2026-12-10T19:00');
    await act(async () =>
        button(view, 'Add availability window').props.onClick(),
    );
    await change(view, 'new-availability-0-starts_at', '2026-12-10T21:00');

    await act(async () =>
        view
            .find((node) => node.type === 'form')
            .props.onSubmit({ preventDefault() {} }),
    );

    assert.deepEqual(submitted, {
        url: '/rosters/9/interest',
        data: {
            position_ids: [4],
            occurrence_date: '2026-12-10',
            availability: [
                { starts_at: '2026-12-10T18:00', ends_at: '2026-12-10T19:00' },
                { starts_at: '2026-12-10T21:00', ends_at: '2026-12-10T22:00' },
            ],
        },
    });
});

await test('controllers can withdraw interest after signups close without editing it', async (t) => {
    let deleted;
    t.mock.method(inertia.router, 'delete', (url, options) => {
        deleted = { url, data: options.data };
    });
    const view = await mount(t, RosterInterestForm, {
        occurrence,
        roster: emptyRoster({
            mode: 'open_interest',
            positions: [{ id: 2, callsign: 'EKCH_A_TWR' }],
        }),
        interest: {
            id: 6,
            user: controller,
            position_ids: [2],
            availability: [
                { starts_at: '2026-12-10T18:00', ends_at: '2026-12-10T20:00' },
            ],
        },
        canSubmit: false,
    });
    assert.equal(
        view.findAll(
            (node) => node.type === 'button' && node.props.type === 'submit',
        ).length,
        0,
    );
    assert.equal(
        view.find(
            (node) =>
                node.type === 'input' &&
                node.props.id === 'availability-0-starts_at',
        ).props.disabled,
        true,
    );

    await act(async () => button(view, 'Withdraw interest').props.onClick());

    assert.deepEqual(deleted, {
        url: '/rosters/9/interest',
        data: { occurrence_date: '2026-12-10' },
    });
});

await test('a controller viewing a closed roster can withdraw their booking and return to the roster directory', async (t) => {
    let deleted;
    t.mock.method(inertia.router, 'delete', (url, options) => {
        deleted = { url, data: options.data };
    });
    const view = await mount(t, RosterPage, {
        event,
        occurrence,
        roster: emptyRoster({
            shifts: [
                {
                    id: 3,
                    name: 'Early',
                    slots: [
                        slot({ booking: controller }),
                        slot({ id: 12, callsign: 'EKCH_APP' }),
                    ],
                },
            ],
        }),
        canManage: false,
        canParticipate: false,
        canViewEvent: false,
        currentUserCid: controller.cid,
    });
    assert.deepEqual(
        view.find(
            (node) =>
                node.type === 'link' && node.props.href.url === '/rosters',
        ).props.href,
        {
            url: '/rosters',
            method: 'get',
        },
    );
    assert.equal(
        view.findAll(
            (node) => node.type === 'button' && text(node) === 'Book position',
        ).length,
        0,
    );
    assert.equal(
        view.findAll(
            (node) =>
                node.type === 'span' &&
                text(node) === '10 Dec 2026, 18:00 – 10 Dec 2026, 20:00 Z',
        ).length,
        2,
    );

    await act(async () => button(view, 'Withdraw booking').props.onClick());

    assert.deepEqual(deleted, {
        url: '/rosters/9/slots/11/booking',
        data: { occurrence_date: '2026-12-10' },
    });
});

await test('only future available slots offer bookings in an open roster', async (t) => {
    let submitted;
    t.mock.method(inertia.router, 'post', (url, data) => {
        submitted = { url, data };
    });
    const view = await mount(t, RosterPage, {
        event,
        occurrence,
        roster: emptyRoster({
            is_open: true,
            shifts: [
                {
                    id: 3,
                    name: 'Early',
                    slots: [
                        slot({ can_book: false }),
                        slot({ id: 12, can_book: true }),
                    ],
                },
            ],
        }),
        canManage: false,
        canParticipate: true,
        canViewEvent: false,
        currentUserCid: controller.cid,
    });

    await act(async () => button(view, 'Book position').props.onClick());

    assert.deepEqual(submitted, {
        url: '/rosters/9/slots/12/booking',
        data: { occurrence_date: '2026-12-10' },
    });
});

await test('an ended roster displays its ended status and offers no new signups', async (t) => {
    const view = await mount(t, RosterPage, {
        event,
        occurrence: { ...occurrence, has_ended: true },
        roster: emptyRoster({
            is_open: true,
            shifts: [{ id: 3, name: 'Early', slots: [slot()] }],
        }),
        canManage: false,
        canParticipate: false,
        canViewEvent: false,
        currentUserCid: controller.cid,
    });

    assert.equal(
        text(
            view.find(
                (node) =>
                    node.type === 'badge' && text(node) === 'Occurrence ended',
            ),
        ),
        'Occurrence ended',
    );
    assert.equal(
        view.findAll(
            (node) => node.type === 'button' && text(node) === 'Book position',
        ).length,
        0,
    );
});

await test('bookings on another occurrence protect the shared roster template', async (t) => {
    const view = await mount(t, RosterEditor, {
        event,
        occurrence,
        onClose() {},
        roster: emptyRoster({
            mode_locked: true,
            shifts: [
                {
                    id: 3,
                    name: 'Early',
                    slots: [slot({ booking: null, is_locked: true })],
                },
            ],
        }),
    });

    assert.equal(
        view.find(
            (node) =>
                node.type === 'input' && node.props.id === 'slot-11-callsign',
        ).props.disabled,
        true,
    );
    assert.equal(
        view.find(
            (node) =>
                node.type === 'input' && node.props.id === 'slot-11-starts_at',
        ).props.disabled,
        true,
    );
    assert.equal(button(view, 'Remove shift').props.disabled, true);
    assert.equal(
        view.findAll((node) => node.type === 'fieldset')[0].props.disabled,
        true,
    );
});

for (const autoSelectOccurrence of [true, false]) {
    await test(`${autoSelectOccurrence ? 'the automatic view advances' : 'an explicitly selected occurrence stays fixed'} after the occurrence ends`, async (t) => {
        t.mock.timers.enable({
            apis: ['Date', 'setTimeout'],
            now: new Date('2026-12-10T21:59:59Z'),
        });
        const visits = [];
        t.mock.method(inertia.router, 'visit', (destination) =>
            visits.push(destination),
        );
        await mount(t, RosterPage, {
            event,
            occurrence,
            roster: emptyRoster(),
            canManage: false,
            canParticipate: false,
            canViewEvent: false,
            currentUserCid: controller.cid,
            autoSelectOccurrence,
        });

        await act(async () => t.mock.timers.tick(2001));

        assert.deepEqual(
            visits,
            autoSelectOccurrence
                ? [{ method: 'get', url: '/events/7/roster' }]
                : [],
        );
    });
}

await test('selecting another occurrence uses its explicit roster URL', async (t) => {
    let destination;
    t.mock.method(inertia.router, 'visit', (route) => {
        destination = route;
    });
    const view = await mount(t, RosterPage, {
        event,
        occurrence,
        roster: emptyRoster(),
        canManage: false,
        canParticipate: false,
        canViewEvent: false,
        currentUserCid: controller.cid,
        occurrenceOptions: [occurrence, { ...occurrence, date: '2026-12-17' }],
    });

    await act(async () =>
        view
            .find((node) => node.type === 'select')
            .props.onValueChange('2026-12-17'),
    );

    assert.deepEqual(destination, {
        method: 'get',
        url: '/events/7/roster/2026-12-17',
    });
});

await test('changing occurrence resets interest availability and submits the displayed date', async (t) => {
    let submitted;
    t.mock.method(inertia.router, 'put', (url, data) => {
        submitted = { url, data };
    });
    const props = {
        event,
        occurrence,
        roster: emptyRoster({
            mode: 'open_interest',
            is_open: true,
            positions: [{ id: 2, callsign: 'EKCH_A_TWR' }],
        }),
        canManage: false,
        canParticipate: true,
        canViewEvent: false,
        currentUserCid: controller.cid,
        occurrenceOptions: [occurrence],
        nextOccurrenceDate: null,
        autoSelectOccurrence: false,
    };
    const view = await mount(t, RosterPage, props);
    await change(view, 'availability-0-starts_at', '2026-12-10T19:00');
    const next = {
        ...occurrence,
        date: '2026-12-17',
        starts_at: '2026-12-17T18:00:00Z',
        ends_at: '2026-12-17T22:00:00Z',
    };

    await view.render(
        createElement(RosterPage, {
            ...props,
            occurrence: next,
            occurrenceOptions: [occurrence, next],
        }),
    );
    await act(async () =>
        view
            .find((node) => node.type === 'checkbox')
            .props.onCheckedChange(true),
    );
    await act(async () =>
        view
            .find((node) => node.type === 'form')
            .props.onSubmit({ preventDefault() {} }),
    );

    assert.deepEqual(submitted, {
        url: '/rosters/9/interest',
        data: {
            occurrence_date: '2026-12-17',
            position_ids: [2],
            availability: [
                { starts_at: '2026-12-17T18:00', ends_at: '2026-12-17T22:00' },
            ],
        },
    });
});

await test('changing occurrence keeps a reused slot booking tied to the displayed date', async (t) => {
    const submissions = [];
    t.mock.method(inertia.router, 'post', (url, data) =>
        submissions.push({ url, data }),
    );
    const props = {
        event,
        occurrence,
        roster: emptyRoster({
            is_open: true,
            shifts: [{ id: 3, name: 'Early', slots: [slot()] }],
        }),
        canManage: false,
        canParticipate: true,
        canViewEvent: false,
        currentUserCid: controller.cid,
        occurrenceOptions: [occurrence],
        nextOccurrenceDate: null,
        autoSelectOccurrence: false,
    };
    const view = await mount(t, RosterPage, props);
    await act(async () => button(view, 'Book position').props.onClick());
    const next = {
        ...occurrence,
        date: '2026-12-17',
        starts_at: '2026-12-17T18:00:00Z',
        ends_at: '2026-12-17T22:00:00Z',
    };

    await view.render(
        createElement(RosterPage, {
            ...props,
            occurrence: next,
            occurrenceOptions: [occurrence, next],
        }),
    );
    await act(async () => button(view, 'Book position').props.onClick());

    assert.deepEqual(submissions, [
        {
            url: '/rosters/9/slots/11/booking',
            data: { occurrence_date: '2026-12-10' },
        },
        {
            url: '/rosters/9/slots/11/booking',
            data: { occurrence_date: '2026-12-17' },
        },
    ]);
});

await test('unavailable daylight saving slots show an explanation and cannot be edited on that occurrence', async (t) => {
    const roster = emptyRoster({
        shifts: [
            {
                id: 3,
                name: 'Early',
                slots: [
                    slot({
                        starts_at: null,
                        ends_at: null,
                        is_unavailable: true,
                        can_book: false,
                    }),
                ],
            },
        ],
    });
    const view = await mount(t, RosterPage, {
        event,
        occurrence,
        roster,
        canManage: false,
        canParticipate: false,
        canViewEvent: false,
        currentUserCid: controller.cid,
    });
    const editor = await mount(t, RosterEditor, {
        event,
        occurrence,
        roster,
        onClose() {},
    });

    assert.equal(
        view.findAll(
            (node) =>
                node.type === 'span' &&
                text(node) === 'Unavailable on this occurrence',
        ).length,
        1,
    );
    assert.equal(editor.findAll((node) => node.type === 'form').length, 0);
    assert.equal(
        text(editor.find((node) => node.type === 'alert-title')),
        'Choose another occurrence to edit the roster',
    );
});

await test('separate booking snapshots retain their original times and withdraw against their own occurrence', async (t) => {
    let submitted;
    t.mock.method(inertia.router, 'delete', (url, options) => {
        submitted = { url, data: options.data };
    });
    const snapshot = {
        id: 51,
        slot_id: null,
        callsign: 'EKCH_B_TWR',
        shift_name: 'Late',
        starts_at: '2026-12-10T20:00',
        ends_at: '2026-12-10T22:00',
        user: controller,
        can_withdraw: true,
    };
    const view = await mount(t, RosterPage, {
        event,
        occurrence,
        roster: emptyRoster({ bookings: [snapshot] }),
        canManage: false,
        canParticipate: false,
        canViewEvent: false,
        currentUserCid: controller.cid,
    });
    assert.equal(
        view.findAll(
            (node) =>
                node.type === 'span' &&
                text(node) ===
                    'Late · 10 Dec 2026, 20:00 – 10 Dec 2026, 22:00 Z',
        ).length,
        1,
    );

    await act(async () => button(view, 'Withdraw booking').props.onClick());

    assert.deepEqual(submitted, {
        url: '/rosters/9/bookings/51',
        data: { occurrence_date: '2026-12-10' },
    });
});

await test('directory cards use the event roster URL and retain events without upcoming occurrences', async (t) => {
    const { default: Directory } = loadComponent(
        new URL('../../resources/js/pages/events/rosters.tsx', import.meta.url),
        { ...ui, ...routes, '@/lib/event-time': { eventTime } },
    );
    const view = await mount(t, Directory, {
        rosters: {
            current_page: 1,
            last_page: 1,
            total: 1,
            data: [
                {
                    id: 9,
                    event_id: 7,
                    title: event.title,
                    owner_code: 'EKDK',
                    mode: 'pre_slotted',
                    is_open: false,
                    has_ended: true,
                    occurrence: null,
                },
            ],
        },
    });

    assert.deepEqual(
        view
            .findAll((node) => node.type === 'link')
            .map((node) => node.props.href),
        [
            { method: 'get', url: '/events/7/roster' },
            { method: 'get', url: '/events/7/roster' },
        ],
    );
    assert.equal(
        view.findAll(
            (node) =>
                node.type === 'p' && text(node) === 'No upcoming occurrences',
        ).length,
        1,
    );
});

await test('past booking snapshots do not lock the shared template when the server permits edits', async (t) => {
    const view = await mount(t, RosterEditor, {
        event,
        occurrence: { ...occurrence, has_ended: true },
        onClose() {},
        roster: emptyRoster({
            mode_locked: false,
            shifts: [
                {
                    id: 3,
                    name: 'Early',
                    slots: [
                        slot({
                            booking: { ...controller, id: 41 },
                            is_locked: false,
                        }),
                    ],
                },
            ],
        }),
    });

    assert.equal(
        view.find(
            (node) =>
                node.type === 'input' && node.props.id === 'slot-11-callsign',
        ).props.disabled,
        false,
    );
    assert.equal(button(view, 'Remove shift').props.disabled, false);
    assert.equal(
        view.findAll((node) => node.type === 'fieldset')[0].props.disabled,
        false,
    );
});

await test('recorded interest remains readable and withdrawable after the event switches roster type', async (t) => {
    let submitted;
    t.mock.method(inertia.router, 'delete', (url, options) => {
        submitted = { url, data: options.data };
    });
    const view = await mount(t, RosterPage, {
        event,
        occurrence,
        roster: emptyRoster({
            mode: 'pre_slotted',
            interests: [
                {
                    id: 5,
                    user: controller,
                    position_ids: [],
                    position_callsigns: ['EKCH_OLD_TWR'],
                    availability: [
                        {
                            starts_at: '2026-12-10T18:00',
                            ends_at: '2026-12-10T20:00',
                        },
                    ],
                },
            ],
        }),
        canManage: false,
        canParticipate: false,
        canViewEvent: false,
        currentUserCid: controller.cid,
    });
    assert.equal(
        view.findAll(
            (node) => node.type === 'badge' && text(node) === 'EKCH_OLD_TWR',
        ).length,
        1,
    );

    await act(async () => button(view, 'Withdraw interest').props.onClick());

    assert.deepEqual(submitted, {
        url: '/rosters/9/interest',
        data: { occurrence_date: '2026-12-10' },
    });
});

await test('closed interest displays the originally selected callsigns after positions are renamed', async (t) => {
    const view = await mount(t, RosterInterestForm, {
        occurrence,
        roster: emptyRoster({
            mode: 'open_interest',
            positions: [{ id: 2, callsign: 'EKCH_NEW_TWR' }],
        }),
        interest: {
            id: 5,
            user: controller,
            position_ids: [2],
            position_callsigns: ['EKCH_OLD_TWR'],
            availability: [
                { starts_at: '2026-12-10T18:00', ends_at: '2026-12-10T20:00' },
            ],
        },
        canSubmit: false,
    });

    assert.equal(
        view.findAll(
            (node) => node.type === 'badge' && text(node) === 'EKCH_OLD_TWR',
        ).length,
        1,
    );
    assert.equal(
        view.findAll(
            (node) => node.type === 'label' && text(node) === 'EKCH_NEW_TWR',
        ).length,
        0,
    );
});

await test('booking from the automatic view keeps its occurrence date and requests return to the current view', async (t) => {
    let submitted;
    t.mock.method(inertia.router, 'post', (url, data) => {
        submitted = { url, data };
    });
    const view = await mount(t, RosterPage, {
        event,
        occurrence,
        roster: emptyRoster({
            is_open: true,
            shifts: [{ id: 3, name: 'Early', slots: [slot()] }],
        }),
        canManage: false,
        canParticipate: true,
        canViewEvent: false,
        currentUserCid: controller.cid,
        autoSelectOccurrence: true,
    });

    await act(async () => button(view, 'Book position').props.onClick());

    assert.deepEqual(submitted, {
        url: '/rosters/9/slots/11/booking',
        data: { occurrence_date: '2026-12-10', return_to_current: true },
    });
});

await test('interest submissions and withdrawals preserve the automatic-view preference', async (t) => {
    const submissions = [];
    t.mock.method(inertia.router, 'put', (url, data) => {
        submissions.push({ method: 'put', url, data });
    });
    t.mock.method(inertia.router, 'delete', (url, options) => {
        submissions.push({ method: 'delete', url, data: options.data });
    });
    const view = await mount(t, RosterInterestForm, {
        occurrence,
        roster: emptyRoster({
            mode: 'open_interest',
            is_open: true,
            positions: [{ id: 2, callsign: 'EKCH_A_TWR' }],
        }),
        interest: {
            id: 5,
            user: controller,
            position_ids: [2],
            position_callsigns: ['EKCH_A_TWR'],
            availability: [
                { starts_at: '2026-12-10T18:00', ends_at: '2026-12-10T20:00' },
            ],
        },
        canSubmit: true,
        autoSelectOccurrence: true,
    });

    await act(async () =>
        view
            .find((node) => node.type === 'form')
            .props.onSubmit({ preventDefault() {} }),
    );
    await act(async () => button(view, 'Withdraw interest').props.onClick());

    assert.deepEqual(submissions, [
        {
            method: 'put',
            url: '/rosters/9/interest',
            data: {
                occurrence_date: '2026-12-10',
                return_to_current: true,
                position_ids: [2],
                availability: [
                    {
                        starts_at: '2026-12-10T18:00',
                        ends_at: '2026-12-10T20:00',
                    },
                ],
            },
        },
        {
            method: 'delete',
            url: '/rosters/9/interest',
            data: { occurrence_date: '2026-12-10', return_to_current: true },
        },
    ]);
});
