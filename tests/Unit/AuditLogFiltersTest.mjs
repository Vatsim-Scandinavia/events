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
