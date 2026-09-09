import assert from 'node:assert/strict';
import { test } from 'node:test';
import {
    recurrenceLabel,
    updateEventStart,
} from '../../resources/js/lib/event-time.ts';

for (const [schedule, interval, label] of [
    ['monthly', 1, 'The last Sunday every month'],
    ['quarterly', 3, 'The last Sunday every 3 months'],
]) {
    await test(`editing only the start time preserves a ${schedule} last-weekday rule`, () => {
        const data = {
            local_start: '2026-03-29T18:00',
            local_end: '2026-03-29T21:00',
            recurrence: 'monthly',
            recurrence_interval: interval,
            monthly_week: -1,
        };

        const result = updateEventStart(data, '2026-03-29T19:30');

        assert.deepEqual(result, {
            ...data,
            local_start: '2026-03-29T19:30',
        });
        assert.equal(recurrenceLabel(result), label);
    });
}

for (const [change, localStart, monthlyWeek] of [
    ['day', '2026-03-08T18:00', 2],
    ['month', '2026-04-29T18:00', 5],
]) {
    await test(`changing the start ${change} recalculates its weekday position`, () => {
        const data = {
            local_start: '2026-03-29T18:00',
            monthly_week: -1,
        };

        const result = updateEventStart(data, localStart);

        assert.deepEqual(result, {
            local_start: localStart,
            monthly_week: monthlyWeek,
        });
    });
}

await test('choosing the first start date initializes its weekday position', () => {
    const result = updateEventStart(
        { local_start: '', monthly_week: 1 },
        '2026-03-29T18:00',
    );

    assert.deepEqual(result, {
        local_start: '2026-03-29T18:00',
        monthly_week: 5,
    });
});

await test('clearing the start input preserves its selected weekday position', () => {
    const result = updateEventStart(
        { local_start: '2026-03-29T18:00', monthly_week: -1 },
        '',
    );

    assert.deepEqual(result, { local_start: '', monthly_week: -1 });
});
