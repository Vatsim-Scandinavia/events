import assert from 'node:assert/strict';
import { test } from 'node:test';
import { router, useForm } from '@inertiajs/react';
import { createElement } from 'react';
import {
    act,
    loadComponent,
    renderComponent,
} from './helpers/render-component.mjs';

const { default: InputError } = loadComponent(
    new URL('../../resources/js/components/input-error.tsx', import.meta.url),
    { '@/lib/utils': { cn: (...classes) => classes.join(' ') } },
);
const index = Object.assign(() => ({ url: '/audit-logs', method: 'get' }), {
    url: () => '/audit-logs',
});
const { default: AuditLogs } = loadComponent(
    new URL('../../resources/js/pages/audit-logs/index.tsx', import.meta.url),
    {
        '@inertiajs/react': { useForm, Head: () => null, Link: 'a' },
        'lucide-react': {
            ChevronLeft: 'svg',
            ChevronRight: 'svg',
            History: 'svg',
            Search: 'svg',
        },
        '@/components/input-error': { __esModule: true, default: InputError },
        '@/components/ui/badge': { Badge: 'span' },
        '@/components/ui/button': { Button: 'button' },
        '@/components/ui/input': { Input: 'input' },
        '@/components/ui/label': { Label: 'label' },
        '@/components/ui/select': {
            Select: 'select',
            SelectContent: 'div',
            SelectGroup: 'div',
            SelectItem: 'option',
            SelectTrigger: 'button',
            SelectValue: 'span',
        },
        '@/components/ui/spinner': { Spinner: 'span' },
        '@/routes/audit-logs': { index },
    },
);

await test('rejected audit filters retain entered dates and show an error until corrected', async (t) => {
    const filters = {
        search: '',
        subject_type: '',
        event: '',
        from: '',
        to: '',
    };
    const logs = {
        data: [],
        current_page: 1,
        last_page: 1,
        from: null,
        to: null,
        total: 0,
    };
    let pageKey = 0;
    let visit;
    t.mock.method(router, 'get', (url, data, options) => {
        visit = { url, data, options };
        options.onBefore({});
        options.onStart({});
    });
    const view = await renderComponent(
        createElement(AuditLogs, { key: pageKey, filters, logs }),
    );
    t.after(() => view.unmount());
    const input = (name) =>
        view.find((node) => node.type === 'input' && node.props.name === name);
    const change = (name, value) =>
        act(() => input(name).props.onChange({ target: { value } }));
    const submit = () =>
        act(() =>
            view
                .find((node) => node.type === 'form')
                .props.onSubmit({
                    preventDefault() {},
                }),
        );
    const respond = async (responseFilters, errors) => {
        // Model the navigation boundary: Inertia swaps the page before onError.
        const preserveState =
            visit.options.preserveState === 'errors'
                ? Object.keys(errors).length > 0
                : visit.options.preserveState === true;
        if (!preserveState) pageKey += 1;
        await view.render(
            createElement(AuditLogs, {
                key: pageKey,
                filters: responseFilters,
                logs,
            }),
        );
        await act(async () => {
            if (Object.keys(errors).length > 0) {
                visit.options.onError(errors);
            } else {
                await visit.options.onSuccess({ props: { errors } });
            }
            visit.options.onFinish({});
        });
    };
    const error = 'The to field must be a date after or equal to from.';

    await change('search', 'EKDK');
    await change('from', '2026-09-09');
    await change('to', '2026-09-08');
    await submit();
    assert.equal(visit.url, '/audit-logs');
    assert.deepEqual(visit.data, {
        ...filters,
        search: 'EKDK',
        from: '2026-09-09',
        to: '2026-09-08',
    });
    await respond(filters, { to: error });

    assert.equal(input('search').props.value, 'EKDK');
    assert.equal(input('from').props.value, '2026-09-09');
    assert.equal(input('to').props.value, '2026-09-08');
    assert.equal(input('to').props['aria-invalid'], true);
    assert.equal(
        view.find((node) => node.props.id === 'audit-to-error').children[0]
            .text,
        error,
    );

    await change('to', '2026-09-10');
    await submit();
    await respond(visit.data, {});

    assert.equal(input('from').props.value, '2026-09-09');
    assert.equal(input('to').props.value, '2026-09-10');
    assert.equal(input('to').props['aria-invalid'], false);
    assert.equal(
        view.findAll((node) => node.props.id === 'audit-to-error').length,
        0,
    );
});

await test('roster actions have readable audit labels and can be selected as filters', async (t) => {
    const actions = {
        booked: 'Position booked',
        withdrawn: 'Booking withdrawn',
        interest_submitted: 'Interest submitted',
        interest_withdrawn: 'Interest withdrawn',
    };
    const filters = {
        search: '',
        subject_type: '',
        event: '',
        from: '',
        to: '',
    };
    const logs = {
        data: Object.keys(actions).map((event, index) => ({
            id: index + 1,
            actor_cid: 1000001,
            actor_name: 'Chris Controller',
            subject_type: 'roster',
            subject_id: 9,
            subject_label: 'Copenhagen staffing · 2026-12-10',
            event,
            source: 'manual',
            old_values: {},
            new_values: {},
            created_at: '2026-12-01T10:00:00Z',
        })),
        current_page: 1,
        last_page: 1,
        from: 1,
        to: 4,
        total: 4,
    };
    let submitted;
    t.mock.method(router, 'get', (url, data) => {
        submitted = { url, data };
    });
    const view = await renderComponent(
        createElement(AuditLogs, { logs, filters }),
    );
    t.after(() => view.unmount());
    const content = (node) => node.text ?? node.children.map(content).join('');
    for (const label of Object.values(actions)) {
        assert.equal(
            view.findAll(
                (node) => node.type === 'span' && content(node) === label,
            ).length,
            1,
        );
    }
    assert.equal(
        view.findAll(
            (node) => node.type === 'span' && content(node) === 'Roster #9',
        ).length,
        4,
    );
    assert.equal(
        content(
            view.find(
                (node) =>
                    node.type === 'option' && node.props.value === 'roster',
            ),
        ),
        'Rosters',
    );

    await act(async () =>
        view
            .findAll((node) => node.type === 'select')[0]
            .props.onValueChange('roster'),
    );
    await act(async () =>
        view
            .findAll((node) => node.type === 'select')[1]
            .props.onValueChange('booked'),
    );
    await act(async () =>
        view
            .find((node) => node.type === 'form')
            .props.onSubmit({ preventDefault() {} }),
    );

    assert.deepEqual(submitted, {
        url: '/audit-logs',
        data: { ...filters, subject_type: 'roster', event: 'booked' },
    });
});
