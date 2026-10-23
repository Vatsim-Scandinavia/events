import { useForm } from '@inertiajs/react';
import type { FormEvent } from 'react';
import { useConfirm } from '@/components/confirm-dialog';
import BeforeStartPicker from '@/components/staffing/before-start-picker';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Field } from '@/components/ui/field';
import { plural } from '@/lib/utils';
import type { StaffingMode } from '@/types/staffing';
import type { StaffingPageProps } from './draft';

const modes: { value: StaffingMode; label: string; description: string }[] = [
    { value: 'none', label: 'Not staffed through the hub', description: 'Staffing is hidden on the event page.' },
    {
        value: 'shifts',
        label: 'Pre-defined shifts',
        description: 'You lay out shifts per position; controllers book them, first come, first served.',
    },
    {
        value: 'open_interest',
        label: 'Open interest',
        description: 'Controllers show interest and availability; you match them to positions and publish a roster.',
    },
];

const switching: Record<StaffingMode, string> = {
    none: 'Switching staffing off',
    shifts: 'Switching to pre-defined shifts',
    open_interest: 'Switching to open interest',
};

// What switching away from the current mode will clear, or null when there's nothing to lose.
function clearedBySwitching({ settings, positions, interests, roster }: StaffingPageProps, to: StaffingMode): string | null {
    const lost: string[] = [];
    const shifts = positions.flatMap((position) => position.shifts);
    const bookings = shifts.filter((shift) => shift.booking).length;

    if (settings.mode === 'shifts' && shifts.length > 0) {
        lost.push(
            `all ${plural(shifts.length, 'shift')}${bookings ? ` (${bookings} booked, so ${bookings === 1 ? 'that controller is' : 'those controllers are'} unbooked)` : ''}`,
        );
    }

    if (settings.mode === 'open_interest') {
        if (interests.length > 0) {
            lost.push(`the interest ${plural(interests.length, 'controller')} have shown`);
        }

        if (roster.length > 0) {
            lost.push(`the roster (${plural(roster.length, 'slot')})`);
        }
    }

    if (to === 'none' && positions.length > 0) {
        lost.push(`all ${plural(positions.length, 'position')}`);
    }

    return lost.length > 0 ? new Intl.ListFormat('en-GB').format(lost) : null;
}

export function SettingsCard(props: StaffingPageProps) {
    const { event, settings, occurrence } = props;
    const confirm = useConfirm();
    const form = useForm({
        mode: settings.mode,
        interest_opens_hours: settings.interestOpensHours,
        interest_closes_hours: settings.interestClosesHours,
    });

    const submit = async (e: FormEvent) => {
        e.preventDefault();
        const cleared = form.data.mode !== settings.mode ? clearedBySwitching(props, form.data.mode) : null;

        if (
            cleared &&
            !(await confirm({
                title: 'Change how controllers sign up?',
                description: `${switching[form.data.mode]} clears ${cleared}.${form.data.mode !== 'none' && props.positions.length > 0 ? ' Positions are kept.' : ''} This can’t be undone.`,
                confirmLabel: 'Change and clear',
                destructive: true,
            }))
        ) {
            return;
        }

        form.put(`/events/${event.id}/staffing`, { preserveScroll: true });
    };

    return (
        <Card>
            <CardHeader>
                <CardTitle>How controllers sign up</CardTitle>
            </CardHeader>
            <CardContent>
                <form onSubmit={submit} className="flex flex-col gap-5">
                    <fieldset className="grid gap-2 md:grid-cols-3">
                        <legend className="sr-only">Staffing mode</legend>
                        {modes.map((mode) => (
                            <label
                                key={mode.value}
                                className="flex cursor-pointer gap-3 rounded-lg p-3 ring-1 ring-foreground/10 has-checked:bg-primary/10 has-checked:ring-primary/50"
                            >
                                <input
                                    type="radio"
                                    name="mode"
                                    className="mt-0.5 size-4 accent-primary"
                                    checked={form.data.mode === mode.value}
                                    onChange={() => form.setData('mode', mode.value)}
                                />
                                <span className="flex flex-col gap-1">
                                    <span className="text-sm font-medium">{mode.label}</span>
                                    <span className="text-xs text-muted-foreground">{mode.description}</span>
                                </span>
                            </label>
                        ))}
                    </fieldset>

                    {form.data.mode === 'open_interest' && (
                        <div className="flex flex-col gap-2">
                            <div className="grid gap-4 sm:grid-cols-2">
                                <Field label="Interest opens (UTC)" error={form.errors.interest_opens_hours}>
                                    <BeforeStartPicker
                                        startsAt={occurrence.startsAt}
                                        hours={form.data.interest_opens_hours}
                                        onChange={(hours) => form.setData('interest_opens_hours', hours)}
                                        min={form.data.interest_closes_hours + 1}
                                        max={2160}
                                    />
                                </Field>
                                <Field label="Interest closes (UTC)" error={form.errors.interest_closes_hours}>
                                    <BeforeStartPicker
                                        startsAt={occurrence.startsAt}
                                        hours={form.data.interest_closes_hours}
                                        onChange={(hours) => form.setData('interest_closes_hours', hours)}
                                        min={0}
                                        max={form.data.interest_opens_hours - 1}
                                    />
                                </Field>
                            </div>
                        </div>
                    )}

                    <Button type="submit" className="self-start" disabled={form.processing || !form.isDirty}>
                        Save
                    </Button>
                </form>
            </CardContent>
        </Card>
    );
}
