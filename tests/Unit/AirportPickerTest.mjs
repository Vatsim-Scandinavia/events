import assert from 'node:assert/strict';
import { test } from 'node:test';
import * as inertia from '@inertiajs/react';
import { createElement, useState } from 'react';
import {
    act,
    loadComponent,
    renderComponent,
} from './helpers/render-component.mjs';

const { http } = inertia;
const { AirportPicker } = loadComponent(
    new URL(
        '../../resources/js/components/airport-picker.tsx',
        import.meta.url,
    ),
    {
        '@inertiajs/react': inertia,
        'lucide-react': { Search: 'search-icon', X: 'remove-icon' },
        sonner: { toast: { error: assert.fail } },
        '@/components/airport-dialog': { AirportDialog: 'airport-dialog' },
        '@/components/event-field': { EventField: 'event-field' },
        '@/components/ui/button': { Button: 'button' },
        '@/components/ui/input': { Input: 'input' },
        '@/components/ui/spinner': { Spinner: 'spinner' },
        '@/routes/airports': {
            index: {
                url: ({ query }) => `/airports?icao=${query.icao}`,
            },
        },
    },
);

const copenhagen = {
    id: 1,
    icao: 'EKCH',
    name: 'Copenhagen',
    country: 'Denmark',
};
const billund = { id: 2, icao: 'EKBI', name: 'Billund', country: 'Denmark' };
const aalborg = { id: 3, icao: 'EKYT', name: 'Aalborg', country: 'Denmark' };

async function pendingLookup(t, selected) {
    t.mock.timers.enable({ apis: ['setTimeout'] });
    globalThis.window = { setTimeout, location: new URL('http://localhost') };
    t.after(() => delete globalThis.window);
    const response = Promise.withResolvers();
    const originalClient = http.getClient();
    const requests = [];
    http.setClient({
        request(config) {
            requests.push(config);
            return response.promise;
        },
    });
    t.after(() => http.setClient(originalClient));
    let setSelection;
    const changes = [];
    function Form() {
        const [airports, setAirports] = useState(selected);
        setSelection = setAirports;
        return createElement(AirportPicker, {
            airports,
            onChange(next) {
                changes.push(next);
                setAirports(next);
            },
            disabled: false,
        });
    }
    const view = await renderComponent(createElement(Form));
    t.after(() => view.unmount());
    await act(async () => {
        view.find((node) => node.type === 'input').props.onChange({
            target: { value: aalborg.icao },
        });
    });
    await act(async () => {
        view.find(
            (node) => node.type === 'button' && !node.props['aria-label'],
        ).props.onClick();
    });
    assert.equal(requests.length, 1);
    assert.equal(requests[0].url, '/airports?icao=EKYT');
    assert.equal(
        view.find((node) => node.type === 'input').props.disabled,
        true,
    );

    return {
        view,
        changes,
        setSelection: (airports) => act(async () => setSelection(airports)),
        resolve: () =>
            act(async () => {
                response.resolve({
                    status: 200,
                    headers: {},
                    data: JSON.stringify({ airport: aalborg }),
                });
            }),
    };
}

function selectedAirports(view) {
    return view
        .findAll((node) => node.type === 'button' && node.props['aria-label'])
        .map((node) => node.props['aria-label']);
}

await test('a delayed lookup preserves removals and the remaining selection', async (t) => {
    const { view, resolve, changes } = await pendingLookup(t, [
        copenhagen,
        billund,
    ]);
    const remove = view.find(
        (node) => node.props['aria-label'] === 'Remove EKCH',
    );
    assert.equal(remove.props.disabled, false);

    await act(async () => remove.props.onClick());
    assert.deepEqual(selectedAirports(view), ['Remove EKBI']);
    await resolve();

    assert.deepEqual(selectedAirports(view), ['Remove EKBI', 'Remove EKYT']);
    assert.deepEqual(changes.at(-1), [billund, aalborg]);
    assert.equal(view.find((node) => node.type === 'input').props.value, '');
});

await test('a delayed lookup does not duplicate an airport selected while it was pending', async (t) => {
    const { view, resolve, setSelection, changes } = await pendingLookup(t, [
        copenhagen,
    ]);

    await setSelection([copenhagen, billund, aalborg]);
    await resolve();

    assert.deepEqual(selectedAirports(view), [
        'Remove EKCH',
        'Remove EKBI',
        'Remove EKYT',
    ]);
    assert.deepEqual(changes, []);
});
