import assert from 'node:assert/strict';
import { test } from 'node:test';
import * as inertia from '@inertiajs/react';
import { createElement } from 'react';
import {
    eventTime,
    recurrenceLabel,
} from '../../resources/js/lib/event-time.ts';
import {
    act,
    loadComponent,
    renderComponent,
} from './helpers/render-component.mjs';

let signedIn = false;
let canManageEvents = false;
const query = (values) =>
    values ? '?' + new URLSearchParams(values).toString() : '';
const publicEventsIndex = Object.assign(
    (options) => ({ method: 'get', url: '/events' + query(options?.query) }),
    { url: () => '/events' },
);
const publicEventShow = Object.assign(
    (id, options) => ({
        method: 'get',
        url: `/events/${id}` + query(options?.query),
    }),
    { url: (id) => `/events/${id}` },
);
const ui = {
    '@inertiajs/react': {
        ...inertia,
        Head: 'head',
        Link: 'link',
        usePage: () => ({
            props: {
                name: 'Events',
                auth: {
                    user: signedIn ? { cid: 1000001 } : null,
                    can_view_events: canManageEvents,
                },
            },
        }),
    },
    'lucide-react': {
        ArrowLeft: 'back-icon',
        CalendarDays: 'calendar-icon',
        ChevronLeft: 'previous-icon',
        ChevronRight: 'next-icon',
        Search: 'search-icon',
        Globe: 'globe-icon',
        LockKeyhole: 'lock-icon',
        Plus: 'plus-icon',
        Ban: 'ban-icon',
        Pencil: 'edit-icon',
        RotateCcw: 'restore-icon',
        X: 'remove-icon',
    },
    sonner: { toast: { success() {} } },
    '@/components/event-field': { EventField: 'event-field' },
    '@/components/event-banner': { EventBanner: 'event-banner' },
    '@/components/input-error': { __esModule: true, default: 'input-error' },
    '@/components/alert-error': { __esModule: true, default: 'alert-error' },
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
        CardFooter: 'card-footer',
    },
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
    '@/components/ui/textarea': { Textarea: 'textarea' },
    '@/components/ui/dialog': {
        Dialog: 'dialog',
        DialogContent: 'dialog-content',
        DialogDescription: 'dialog-description',
        DialogFooter: 'dialog-footer',
        DialogHeader: 'dialog-header',
        DialogTitle: 'dialog-title',
    },
    '@/components/ui/spinner': { Spinner: 'spinner' },
    '@/components/ui/toggle-group': {
        ToggleGroup: 'toggle-group',
        ToggleGroupItem: 'toggle-group-item',
    },
    '@/lib/event-time': { eventTime, recurrenceLabel },
    '@/routes': {
        dashboard: () => ({ method: 'get', url: '/dashboard' }),
        login: () => ({ method: 'get', url: '/login' }),
    },
    '@/routes/events': {
        index: publicEventsIndex,
        show: publicEventShow,
        edit: (id) => ({ method: 'get', url: `/events/${id}/edit` }),
        create: () => ({ method: 'get', url: '/events/create' }),
    },
    '@/routes/events/collaborations': {
        store: { url: (id) => `/events/${id}/collaborations` },
        destroy: {
            url: ({ event, collaboration }) =>
                `/events/${event}/collaborations/${collaboration}`,
        },
        update: {
            url: ({ event, collaboration }) =>
                `/events/${event}/collaborations/${collaboration}`,
        },
    },
    '@/routes/events/cancellations': {
        store: { url: (id) => `/events/${id}/cancellations` },
        destroy: (id) => ({
            method: 'delete',
            url: `/events/${id}/cancellations`,
        }),
    },
    '@/routes/events/roster': {
        show: ({ event, date }) => ({
            method: 'get',
            url: `/events/${event}/roster${date ? '/' + date : ''}`,
        }),
    },
    '@/routes/events/publication': {
        store: (id) => ({ method: 'post', url: `/events/${id}/publication` }),
        destroy: (id) => ({
            method: 'delete',
            url: `/events/${id}/publication`,
        }),
    },
};
const { EventPublicationControls } = loadComponent(
    new URL(
        '../../resources/js/components/event-publication-controls.tsx',
        import.meta.url,
    ),
    ui,
);
ui['@/components/event-publication-controls'] = { EventPublicationControls };
const { default: PublicEvents } = loadComponent(
    new URL('../../resources/js/pages/events/index.tsx', import.meta.url),
    ui,
);
const { default: PublicShow } = loadComponent(
    new URL('../../resources/js/pages/events/show.tsx', import.meta.url),
    ui,
);
const { default: PublicLayout } = loadComponent(
    new URL('../../resources/js/layouts/public-layout.tsx', import.meta.url),
    ui,
);
function PrivateLayout({ children }) {
    if (!signedIn)
        throw new Error('Public pages must not use an authenticated layout.');
    return createElement('staff-layout', null, children);
}
const { resolvePageLayout } = loadComponent(
    new URL(
        '../../resources/js/layouts/resolve-page-layout.ts',
        import.meta.url,
    ),
    {
        '@/layouts/app-layout': { __esModule: true, default: PrivateLayout },
        '@/layouts/auth-layout': { __esModule: true, default: PrivateLayout },
        '@/layouts/settings/layout': {
            __esModule: true,
            default: PrivateLayout,
        },
        '@/layouts/public-layout': { __esModule: true, default: PublicLayout },
    },
);
const occurrence = {
    date: '2026-12-17',
    starts_at: '2026-12-17T18:00:00Z',
    ends_at: '2026-12-17T22:00:00Z',
    status: 'scheduled',
    reason: null,
};
function publicEvent(overrides = {}) {
    return {
        id: 7,
        title: 'Copenhagen evening',
        short_description_html: '<p>Fly with us</p>',
        description_html: '<p><strong>Bring charts</strong></p>',
        status: 'published',
        timezone: 'UTC',
        local_start: '2026-12-10T18:00',
        recurrence: 'weekly',
        recurrence_interval: 1,
        monthly_week: 2,
        recurrence_until: null,
        starts_at: '2026-12-10T18:00:00Z',
        ends_at: '2026-12-10T22:00:00Z',
        owner: { id: 1, code: 'EKDK', name: 'Denmark' },
        airports: [
            { id: 1, icao: 'EKCH', name: 'Copenhagen', country: 'Denmark' },
        ],
        banner_url: '/events/7/banner',
        occurrence,
        cancellation_reason: null,
        ...overrides,
    };
}
function pagination(data, overrides = {}) {
    return {
        data,
        current_page: 1,
        last_page: 1,
        total: data.length,
        ...overrides,
    };
}
function text(node) {
    return node.text ?? node.children.map(text).join('');
}
function button(view, label) {
    return view.find((node) => node.type === 'button' && text(node) === label);
}
async function mount(t, component, props, layoutName) {
    const page = createElement(component, props);
    const view = await renderComponent(
        layoutName
            ? createElement(resolvePageLayout(layoutName), null, page)
            : page,
    );
    t.after(() => view.unmount());
    return view;
}

await test('guests browse public events in a public layout showing the next occurrence', async (t) => {
    const view = await mount(
        t,
        PublicEvents,
        {
            events: pagination([publicEvent()]),
            filters: { search: '', status: '' },
        },
        'events/index',
    );

    assert.equal(
        view.findAll((node) => node.type === 'link' && text(node) === 'Sign in')
            .length,
        1,
    );
    assert.equal(
        view.findAll(
            (node) =>
                node.type === 'link' &&
                /Create event|Edit event|Staff view|View public event/.test(
                    text(node),
                ),
        ).length,
        0,
    );
    assert.equal(
        view.findAll(
            (node) =>
                node.type === 'p' && text(node) === '17 Dec 2026, 18:00 Z',
        ).length,
        1,
    );
    assert.equal(
        view.findAll(
            (node) => node.type === 'option' && node.props.value === 'draft',
        ).length,
        0,
    );
    assert.equal(
        view.findAll(
            (node) =>
                node.type === 'alert-title' &&
                text(node) === 'Collaboration invitations',
        ).length,
        0,
    );
    assert.equal(
        view.find((node) => node.type === 'event-banner').props.src,
        '/events/7/banner',
    );
});

await test('signed-in visitors keep public browsing and can return to their dashboard', async (t) => {
    signedIn = true;
    t.after(() => {
        signedIn = false;
    });
    const view = await mount(
        t,
        PublicEvents,
        { events: pagination([]), filters: { search: '', status: '' } },
        'events/index',
    );

    assert.equal(
        view.find((node) => node.type === 'link' && text(node) === 'Dashboard')
            .props.href.url,
        '/dashboard',
    );
    assert.equal(
        view.findAll((node) => node.type === 'link' && text(node) === 'Sign in')
            .length,
        0,
    );
});

await test('public search preserves rejected input and pagination carries its search', async (t) => {
    let request;
    t.mock.method(inertia.router, 'get', (url, data, options) => {
        request = { url, data, preserveState: options.preserveState };
        options.onError({ search: 'Search must be at most 100 characters.' });
    });
    const view = await mount(t, PublicEvents, {
        events: pagination([publicEvent()], { last_page: 2, total: 13 }),
        filters: { search: 'Copenhagen', status: '' },
    });
    assert.equal(
        view.find((node) => node.type === 'link' && text(node) === 'Next').props
            .href.url,
        '/events?search=Copenhagen&status=&page=2',
    );
    await act(async () =>
        view
            .find((node) => node.type === 'input')
            .props.onChange({ target: { value: 'Copenhagen search' } }),
    );

    await act(async () =>
        view
            .find((node) => node.type === 'form')
            .props.onSubmit({ preventDefault() {} }),
    );

    assert.deepEqual(request, {
        url: '/events',
        data: { search: 'Copenhagen search', status: '' },
        preserveState: 'errors',
    });
    assert.equal(
        view.find((node) => node.type === 'input').props.value,
        'Copenhagen search',
    );
    assert.equal(
        view.find(
            (node) =>
                node.type === 'input-error' &&
                node.props.id === 'event-search-error',
        ).props.message,
        'Search must be at most 100 characters.',
    );
});

await test('public event details retain cancellation notices and sanitized event descriptions for guests', async (t) => {
    const event = publicEvent({
        status: 'cancelled',
        cancellation_reason: 'Airport staffing unavailable',
        airports: [],
    });
    const cancelled = {
        ...occurrence,
        status: 'cancelled',
        reason: 'Local staffing unavailable',
    };
    const view = await mount(
        t,
        PublicShow,
        {
            event,
            occurrences: [cancelled],
            from: '2026-12-17',
            next_from: null,
        },
        'events/show',
    );

    assert.equal(
        view.findAll(
            (node) =>
                node.type === 'p' && text(node) === 'No airports specified.',
        ).length,
        1,
    );
    assert.equal(
        text(view.find((node) => node.type === 'alert-title')),
        'Event cancelled',
    );
    assert.equal(
        text(view.find((node) => node.type === 'alert-description')),
        'Airport staffing unavailable',
    );
    assert.equal(
        view.findAll(
            (node) =>
                node.type === 'p' &&
                text(node) === 'Local staffing unavailable',
        ).length,
        1,
    );
    assert.equal(
        view.find(
            (node) =>
                node.props.dangerouslySetInnerHTML?.__html ===
                event.description_html,
        ).props.dangerouslySetInnerHTML.__html,
        '<p><strong>Bring charts</strong></p>',
    );
    assert.equal(
        view.findAll(
            (node) =>
                node.type === 'link' &&
                /Create event|Edit event|Staff view|View public event/.test(
                    text(node),
                ),
        ).length,
        0,
    );
});

await test('public occurrence filters and later dates use the public route', async (t) => {
    let request;
    t.mock.method(inertia.router, 'get', (url, data, options) => {
        request = { url, data, preserveState: options.preserveState };
    });
    const view = await mount(t, PublicShow, {
        event: publicEvent(),
        occurrences: [occurrence],
        from: '2026-12-17',
        next_from: '2027-03-11',
    });
    assert.equal(
        view.find(
            (node) =>
                node.type === 'link' && text(node) === 'Later occurrences',
        ).props.href.url,
        '/events/7?from=2027-03-11',
    );
    await act(async () =>
        view
            .find((node) => node.type === 'input')
            .props.onChange({ target: { value: '2027-01-01' } }),
    );

    await act(async () =>
        view
            .find((node) => node.type === 'form')
            .props.onSubmit({ preventDefault() {} }),
    );

    assert.deepEqual(request, {
        url: '/events/7',
        data: { from: '2027-01-01' },
        preserveState: 'errors',
    });
});

await test('publishing uses the owner action and shows loading and validation feedback', async (t) => {
    let request;
    t.mock.method(inertia.router, 'post', (url, data, options) => {
        request = { url, data, options };
        options.onBefore({});
        options.onStart({});
    });
    const view = await mount(t, EventPublicationControls, {
        event: { id: 7, status: 'draft' },
        canPublish: true,
        canUnpublish: false,
    });

    await act(async () => button(view, 'Publish event').props.onClick());
    assert.equal(button(view, 'Publish event').props.disabled, true);
    await act(async () => {
        request.options.onError({
            publication: 'Add event details before publishing.',
        });
        request.options.onFinish({});
    });

    assert.equal(request.url, '/events/7/publication');
    assert.deepEqual(request.data, {});
    assert.equal(button(view, 'Publish event').props.disabled, false);
    assert.deepEqual(
        view.find((node) => node.type === 'alert-error').props.errors,
        ['Add event details before publishing.'],
    );
});

await test('a publicly visible cancelled event can be unpublished without a duplicate page link', async (t) => {
    let request;
    t.mock.method(inertia.router, 'delete', (url, options) => {
        request = { url, data: options.data };
    });
    const view = await mount(t, EventPublicationControls, {
        event: { id: 7, status: 'cancelled' },
        canPublish: false,
        canUnpublish: true,
    });
    assert.equal(view.findAll((node) => node.type === 'link').length, 0);

    await act(async () => button(view, 'Unpublish').props.onClick());

    assert.deepEqual(request, { url: '/events/7/publication', data: {} });
});

await test('publication actions remain hidden without owner publication permission', async (t) => {
    const view = await mount(t, EventPublicationControls, {
        event: { id: 7, status: 'draft' },
        canPublish: false,
        canUnpublish: false,
    });

    assert.equal(view.findAll((node) => node.type === 'button').length, 0);
});

await test('published events display their status and can be selected in management filters', async (t) => {
    const { default: Index } = loadComponent(
        new URL('../../resources/js/pages/events/index.tsx', import.meta.url),
        {
            ...ui,
            '@/routes/events': {
                index: { url: () => '/events' },
                show: (id) => ({ url: `/events/${id}`, method: 'get' }),
                create: () => ({ url: '/events/create', method: 'get' }),
            },
            '@/routes/events/collaborations': { update: {} },
        },
    );
    let request;
    t.mock.method(inertia.router, 'get', (url, data) => {
        request = { url, data };
    });
    const view = await mount(t, Index, {
        events: pagination([publicEvent()]),
        filters: { search: '', status: '' },
        can_create: false,
        invitations: [],
    });
    assert.equal(
        view.findAll(
            (node) => node.type === 'badge' && text(node) === 'Published',
        ).length,
        1,
    );
    await act(async () =>
        view
            .find((node) => node.type === 'select')
            .props.onValueChange('published'),
    );

    await act(async () =>
        view
            .find((node) => node.type === 'form')
            .props.onSubmit({ preventDefault() {} }),
    );

    assert.deepEqual(request, {
        url: '/events',
        data: { search: '', status: 'published' },
    });
});

for (const can_view_events of [false, true]) {
    await test(`event navigation has one destination for ${can_view_events ? 'staff' : 'other signed-in visitors'}`, async (t) => {
        signedIn = true;
        canManageEvents = can_view_events;
        t.after(() => {
            signedIn = false;
            canManageEvents = false;
        });
        const view = await mount(
            t,
            PublicEvents,
            { events: pagination([]), filters: { search: '', status: '' } },
            'events/index',
        );
        const nav = view.find(
            (node) =>
                node.type === 'nav' &&
                node.props['aria-label'] === 'Public navigation',
        );
        assert.deepEqual(
            nav.children
                .filter((node) => node.type === 'link')
                .map((node) => [text(node), node.props.href.url]),
            [['Events', '/events']],
        );
        assert.equal(
            view.findAll((node) => node.type === 'staff-layout').length,
            0,
        );
    });
}

function details(overrides = {}) {
    return {
        event: publicEvent(),
        occurrences: [occurrence],
        from: '2026-12-17',
        next_from: null,
        ...overrides,
    };
}
const noActions = {
    edit: false,
    manage_owner: false,
    publish: false,
    unpublish: false,
};

for (const visitor of ['guest', 'unrelated coordinator', 'read-only staff']) {
    await test(`${visitor} sees the shared event content without edit controls`, async (t) => {
        signedIn = visitor !== 'guest';
        canManageEvents = visitor !== 'guest';
        t.after(() => {
            signedIn = false;
            canManageEvents = false;
        });
        const isStaff = visitor === 'read-only staff';
        const view = await mount(
            t,
            PublicShow,
            details(
                isStaff
                    ? {
                          can: noActions,
                          collaborations: [
                              {
                                  id: 4,
                                  team: { id: 2, code: 'ESAA', name: 'Sweden' },
                                  accepted: true,
                              },
                          ],
                          firs: [],
                          event: publicEvent({
                              roster_enabled: true,
                              roster_exists: true,
                          }),
                      }
                    : {},
            ),
            'events/show',
        );
        assert.equal(
            view.findAll(
                (node) =>
                    node.props.dangerouslySetInnerHTML?.__html ===
                    publicEvent().description_html,
            ).length,
            1,
        );
        assert.equal(
            view.findAll(
                (node) =>
                    node.type === 'button' &&
                    /Publish event|Unpublish|Cancel|Restore|Invite FIR/.test(
                        text(node),
                    ),
            ).length,
            0,
        );
        assert.equal(
            view.findAll(
                (node) =>
                    node.type === 'link' &&
                    /Edit event|Staff view|Manage event|View public event/.test(
                        text(node),
                    ),
            ).length,
            0,
        );
        assert.equal(
            view.findAll(
                (node) =>
                    node.type === 'card-title' && text(node) === 'FIR access',
            ).length,
            isStaff ? 1 : 0,
        );
        assert.equal(
            view.findAll(
                (node) => node.type === 'link' && text(node) === 'View roster',
            ).length,
            isStaff ? 2 : 0,
        );
        assert.equal(
            view.findAll((node) => node.type === 'staff-layout').length,
            0,
        );
    });
}

await test('an owner publishes from the shared event page and validation feedback stays beside its controls', async (t) => {
    let request;
    t.mock.method(inertia.router, 'post', (url, data, options) => {
        request = { url, data };
        options.onError({ publication: 'Event details need attention.' });
    });
    const view = await mount(
        t,
        PublicShow,
        details({
            event: publicEvent({
                status: 'draft',
                roster_enabled: true,
                roster_exists: false,
            }),
            can: {
                ...noActions,
                edit: true,
                manage_owner: true,
                publish: true,
            },
            collaborations: [],
            firs: [],
        }),
    );
    assert.equal(
        view.find((node) => node.type === 'link' && text(node) === 'Edit event')
            .props.href.url,
        '/events/7/edit',
    );
    assert.equal(
        view.find(
            (node) => node.type === 'link' && text(node) === 'Manage roster',
        ).props.href.url,
        '/events/7/roster',
    );
    await act(async () => button(view, 'Publish event').props.onClick());
    assert.deepEqual(request, { url: '/events/7/publication', data: {} });
    assert.deepEqual(
        view.find((node) => node.type === 'alert-error').props.errors,
        ['Event details need attention.'],
    );
    assert.equal(
        view.findAll(
            (node) =>
                node.props.dangerouslySetInnerHTML?.__html ===
                publicEvent().description_html,
        ).length,
        1,
    );
});

await test('an accepted coordinator cancels an occurrence from the shared schedule without owner actions', async (t) => {
    let request;
    t.mock.method(inertia.router, 'post', (url, data, options) => {
        request = { url, data };
        options.onError({ reason: 'Reason is too long.' });
    });
    const view = await mount(
        t,
        PublicShow,
        details({
            can: { ...noActions, edit: true },
            collaborations: [],
            firs: [],
        }),
    );
    assert.equal(
        view.findAll(
            (node) =>
                node.type === 'button' &&
                /Cancel series|Publish event|Unpublish|Invite FIR/.test(
                    text(node),
                ),
        ).length,
        0,
    );
    await act(async () =>
        view
            .find(
                (node) =>
                    node.type === 'button' &&
                    node.props['aria-label'] === 'Cancel occurrence 2026-12-17',
            )
            .props.onClick({ currentTarget: {} }),
    );
    await act(async () =>
        view
            .find((node) => node.type === 'textarea')
            .props.onChange({ target: { value: 'Staffing unavailable' } }),
    );
    const dialog = view.find((node) => node.type === 'dialog');
    const form = view.find(
        (node) =>
            node.type === 'form' &&
            node.children.some((child) => child.type === 'event-field'),
    );
    await act(async () => form.props.onSubmit({ preventDefault() {} }));
    assert.deepEqual(request, {
        url: '/events/7/cancellations',
        data: { occurrence_date: '2026-12-17', reason: 'Staffing unavailable' },
    });
    assert.equal(
        view.find((node) => node.type === 'event-field').props.error,
        'Reason is too long.',
    );
    assert.ok(dialog);
});

await test('owner restoration and FIR invitations stay available on the shared event page', async (t) => {
    let restored;
    let invited;
    t.mock.method(inertia.router, 'delete', (url, options) => {
        restored = { url, data: options.data };
    });
    t.mock.method(inertia.router, 'post', (url, data, options) => {
        invited = { url, data };
        options.onError({ team_id: 'This FIR has already been invited.' });
    });
    const view = await mount(
        t,
        PublicShow,
        details({
            event: publicEvent({ status: 'cancelled' }),
            can: { ...noActions, manage_owner: true },
            collaborations: [],
            firs: [{ id: 2, code: 'ESAA', name: 'Sweden' }],
        }),
    );
    await act(async () => button(view, 'Restore series').props.onClick());
    assert.deepEqual(restored, {
        url: '/events/7/cancellations',
        data: { occurrence_date: null },
    });
    await act(async () =>
        view.find((node) => node.type === 'select').props.onValueChange('2'),
    );
    await act(async () =>
        view
            .find(
                (node) =>
                    node.type === 'form' &&
                    node.children.some((child) => child.type === 'select'),
            )
            .props.onSubmit({ preventDefault() {} }),
    );
    assert.deepEqual(invited, {
        url: '/events/7/collaborations',
        data: { team_id: '2' },
    });
    assert.equal(
        view.find((node) => node.type === 'input-error' && node.props.message)
            .props.message,
        'This FIR has already been invited.',
    );
});

await test('scoped event index offers creation and accepts collaboration invitations on the same listing', async (t) => {
    let request;
    t.mock.method(inertia.router, 'patch', (url, data) => {
        request = { url, data };
    });
    const view = await mount(t, PublicEvents, {
        events: pagination([publicEvent({ status: 'draft' })]),
        filters: { search: '', status: '' },
        can_create: true,
        invitations: [
            { id: 5, event_id: 8, owner_code: 'ESAA', team_code: 'EKDK' },
        ],
    });
    assert.equal(
        view.find(
            (node) => node.type === 'link' && text(node) === 'Create event',
        ).props.href.url,
        '/events/create',
    );
    assert.equal(
        view.findAll(
            (node) => node.type === 'option' && node.props.value === 'draft',
        ).length,
        1,
    );
    await act(async () => button(view, 'Accept for EKDK').props.onClick());
    assert.deepEqual(request, { url: '/events/8/collaborations/5', data: {} });
});

await test('private event editing retains the authenticated layout', async (t) => {
    signedIn = true;
    t.after(() => {
        signedIn = false;
    });
    const view = await mount(
        t,
        () => createElement('staff-content'),
        {},
        'events/form',
    );
    assert.equal(
        view.findAll((node) => node.type === 'staff-layout').length,
        1,
    );
    assert.equal(
        view.findAll(
            (node) =>
                node.type === 'nav' &&
                node.props['aria-label'] === 'Public navigation',
        ).length,
        0,
    );
});
