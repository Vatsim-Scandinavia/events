import { useForm } from '@inertiajs/react';
import { Plus, Save, X } from 'lucide-react';
import { useRef } from 'react';
import { toast } from 'sonner';
import AlertError from '@/components/alert-error';
import { EventField } from '@/components/event-field';
import { RosterTimeFields } from '@/components/roster-time-fields';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { ToggleGroup, ToggleGroupItem } from '@/components/ui/toggle-group';
import { update } from '@/routes/events/roster';
import type {
    RosterAvailability,
    RosterMode,
    RosterPageProps,
} from '@/types/rosters';

type EditablePosition = { key: string; id?: number; callsign: string };
type EditableSlot = EditablePosition & RosterAvailability;
type EditableShift = {
    key: string;
    id?: number;
    name: string;
    slots: EditableSlot[];
};
type RosterFormData = {
    mode: RosterMode;
    is_open: boolean;
    shifts: EditableShift[];
    positions: EditablePosition[];
};

export function RosterEditor({
    event,
    occurrence,
    roster,
    onClose,
}: Pick<RosterPageProps, 'event' | 'occurrence' | 'roster'> & {
    onClose: () => void;
}) {
    const nextKey = useRef(0);
    const minimum = occurrence.starts_at?.slice(0, 16) ?? '';
    const maximum = occurrence.ends_at?.slice(0, 16) ?? '';
    const form = useForm<RosterFormData>({
        mode: roster?.mode ?? 'pre_slotted',
        is_open: roster?.is_open ?? false,
        shifts:
            roster?.shifts.map((shift) => ({
                key: 'shift-' + shift.id,
                id: shift.id,
                name: shift.name,
                slots: shift.slots.map((slot) => ({
                    key: 'slot-' + slot.id,
                    id: slot.id,
                    callsign: slot.callsign,
                    starts_at: slot.starts_at.slice(0, 16),
                    ends_at: slot.ends_at.slice(0, 16),
                })),
            })) ?? [],
        positions:
            roster?.positions.map((position) => ({
                ...position,
                key: 'position-' + position.id,
            })) ?? [],
    });
    const errors: Record<string, string | undefined> = form.errors;
    const bookedSlots = new Set(
        roster?.shifts.flatMap((shift) =>
            shift.slots.filter((slot) => slot.booking).map((slot) => slot.id),
        ) ?? [],
    );
    const interestedPositions = new Set(
        roster?.interests.flatMap((interest) => interest.position_ids) ?? [],
    );
    const modeLocked =
        bookedSlots.size > 0 || (roster?.interests.length ?? 0) > 0;
    const changeSlot = (
        shiftIndex: number,
        slotIndex: number,
        changes: Partial<EditableSlot>,
    ) => {
        form.setData(
            'shifts',
            form.data.shifts.map((shift, index) =>
                index === shiftIndex
                    ? {
                          ...shift,
                          slots: shift.slots.map((slot, index) =>
                              index === slotIndex
                                  ? { ...slot, ...changes }
                                  : slot,
                          ),
                      }
                    : shift,
            ),
        );
    };

    return (
        <Card>
            <CardHeader>
                <CardTitle>
                    {roster ? 'Edit roster' : 'Set up a roster'}
                </CardTitle>
                <CardDescription>
                    Configure this occurrence. All staffing times are in UTC and
                    must fall within the event.
                </CardDescription>
            </CardHeader>
            <CardContent>
                <form
                    className="flex flex-col gap-6"
                    onSubmit={(e) => {
                        e.preventDefault();
                        form.transform((data) => ({
                            mode: data.mode,
                            is_open: data.is_open,
                            shifts:
                                data.mode === 'pre_slotted'
                                    ? data.shifts.map((shift) => ({
                                          id: shift.id,
                                          name: shift.name,
                                          slots: shift.slots.map(
                                              ({
                                                  id,
                                                  callsign,
                                                  starts_at,
                                                  ends_at,
                                              }) => ({
                                                  id,
                                                  callsign,
                                                  starts_at,
                                                  ends_at,
                                              }),
                                          ),
                                      }))
                                    : [],
                            positions:
                                data.mode === 'open_interest'
                                    ? data.positions.map(
                                          ({ id, callsign }) => ({
                                              id,
                                              callsign,
                                          }),
                                      )
                                    : [],
                        }));
                        form.submit(
                            update({ event: event.id, date: occurrence.date }),
                            {
                                preserveScroll: true,
                                onSuccess: () => {
                                    toast.success('Roster saved.');
                                    onClose();
                                },
                            },
                        );
                    }}
                >
                    {form.hasErrors ? (
                        <AlertError
                            title="Check the roster details"
                            errors={Object.values(form.errors)}
                        />
                    ) : null}
                    <fieldset
                        className="flex flex-col gap-3"
                        disabled={form.processing || modeLocked}
                    >
                        <legend className="mb-3 text-sm font-medium">
                            Roster type
                        </legend>
                        <ToggleGroup
                            type="single"
                            variant="outline"
                            value={form.data.mode}
                            onValueChange={(value) => {
                                if (
                                    value === 'pre_slotted' ||
                                    value === 'open_interest'
                                )
                                    form.setData('mode', value);
                            }}
                            className="w-fit"
                            aria-label="Roster type"
                        >
                            <ToggleGroupItem value="pre_slotted">
                                Pre-slotted
                            </ToggleGroupItem>
                            <ToggleGroupItem value="open_interest">
                                Open interest
                            </ToggleGroupItem>
                        </ToggleGroup>
                        <p className="text-muted-foreground text-sm">
                            {modeLocked
                                ? 'The roster type is fixed while bookings or interest submissions exist.'
                                : form.data.mode === 'pre_slotted'
                                  ? 'Create shifts and positions that controllers can book.'
                                  : 'Choose positions so controllers can submit their interest and availability.'}
                        </p>
                    </fieldset>
                    {form.data.mode === 'pre_slotted' ? (
                        <div className="flex flex-col gap-4">
                            {form.data.shifts.map((shift, shiftIndex) => {
                                const shiftLocked = shift.slots.some(
                                    (slot) =>
                                        slot.id !== undefined &&
                                        bookedSlots.has(slot.id),
                                );
                                return (
                                    <fieldset
                                        key={shift.key}
                                        className="flex min-w-0 flex-col gap-4 rounded-lg border p-4"
                                        disabled={form.processing}
                                    >
                                        <legend className="px-1 text-sm font-medium">
                                            Shift {shiftIndex + 1}
                                        </legend>
                                        <div className="flex items-start gap-3">
                                            <div className="min-w-0 flex-1">
                                                <EventField
                                                    id={shift.key + '-name'}
                                                    label="Shift name"
                                                    error={
                                                        errors[
                                                            'shifts.' +
                                                                shiftIndex +
                                                                '.name'
                                                        ]
                                                    }
                                                >
                                                    <Input
                                                        id={shift.key + '-name'}
                                                        value={shift.name}
                                                        placeholder="Early, late, main…"
                                                        required
                                                        maxLength={100}
                                                        onChange={(e) =>
                                                            form.setData(
                                                                'shifts',
                                                                form.data.shifts.map(
                                                                    (
                                                                        item,
                                                                        index,
                                                                    ) =>
                                                                        index ===
                                                                        shiftIndex
                                                                            ? {
                                                                                  ...item,
                                                                                  name: e
                                                                                      .target
                                                                                      .value,
                                                                              }
                                                                            : item,
                                                                ),
                                                            )
                                                        }
                                                        aria-invalid={
                                                            !!errors[
                                                                'shifts.' +
                                                                    shiftIndex +
                                                                    '.name'
                                                            ]
                                                        }
                                                        aria-describedby={
                                                            shift.key +
                                                            '-name-error'
                                                        }
                                                    />
                                                </EventField>
                                            </div>
                                            <Button
                                                type="button"
                                                variant="ghost"
                                                size="sm"
                                                disabled={shiftLocked}
                                                onClick={() =>
                                                    form.setData(
                                                        'shifts',
                                                        form.data.shifts.filter(
                                                            (_, index) =>
                                                                index !==
                                                                shiftIndex,
                                                        ),
                                                    )
                                                }
                                                aria-label={
                                                    'Remove shift ' +
                                                    (shift.name ||
                                                        shiftIndex + 1)
                                                }
                                            >
                                                Remove shift
                                            </Button>
                                        </div>
                                        {shift.slots.map((slot, slotIndex) => {
                                            const path =
                                                'shifts.' +
                                                shiftIndex +
                                                '.slots.' +
                                                slotIndex;
                                            const locked =
                                                slot.id !== undefined &&
                                                bookedSlots.has(slot.id);
                                            return (
                                                <div
                                                    key={slot.key}
                                                    className="flex flex-col gap-2"
                                                >
                                                    <div className="grid min-w-0 items-start gap-3 lg:grid-cols-[minmax(0,1fr)_minmax(0,1fr)_minmax(0,1fr)_auto]">
                                                        <EventField
                                                            id={
                                                                slot.key +
                                                                '-callsign'
                                                            }
                                                            label="Position callsign"
                                                            error={
                                                                errors[
                                                                    path +
                                                                        '.callsign'
                                                                ]
                                                            }
                                                        >
                                                            <Input
                                                                id={
                                                                    slot.key +
                                                                    '-callsign'
                                                                }
                                                                value={
                                                                    slot.callsign
                                                                }
                                                                placeholder="EKCH_A_TWR"
                                                                className="font-mono"
                                                                required
                                                                maxLength={30}
                                                                disabled={
                                                                    locked
                                                                }
                                                                onChange={(e) =>
                                                                    changeSlot(
                                                                        shiftIndex,
                                                                        slotIndex,
                                                                        {
                                                                            callsign:
                                                                                e.target.value.toUpperCase(),
                                                                        },
                                                                    )
                                                                }
                                                                aria-invalid={
                                                                    !!errors[
                                                                        path +
                                                                            '.callsign'
                                                                    ]
                                                                }
                                                                aria-describedby={
                                                                    slot.key +
                                                                    '-callsign-error'
                                                                }
                                                            />
                                                        </EventField>
                                                        <RosterTimeFields
                                                            id={slot.key}
                                                            value={slot}
                                                            minimum={minimum}
                                                            maximum={maximum}
                                                            disabled={
                                                                locked ||
                                                                form.processing
                                                            }
                                                            errors={{
                                                                starts_at:
                                                                    errors[
                                                                        path +
                                                                            '.starts_at'
                                                                    ],
                                                                ends_at:
                                                                    errors[
                                                                        path +
                                                                            '.ends_at'
                                                                    ],
                                                            }}
                                                            onChange={(
                                                                field,
                                                                value,
                                                            ) =>
                                                                changeSlot(
                                                                    shiftIndex,
                                                                    slotIndex,
                                                                    {
                                                                        [field]:
                                                                            value,
                                                                    },
                                                                )
                                                            }
                                                        />
                                                        <Button
                                                            type="button"
                                                            variant="ghost"
                                                            size="icon"
                                                            className="self-end"
                                                            disabled={locked}
                                                            aria-label={
                                                                'Remove position ' +
                                                                (slot.callsign ||
                                                                    slotIndex +
                                                                        1)
                                                            }
                                                            onClick={() =>
                                                                form.setData(
                                                                    'shifts',
                                                                    form.data.shifts.map(
                                                                        (
                                                                            item,
                                                                            index,
                                                                        ) =>
                                                                            index ===
                                                                            shiftIndex
                                                                                ? {
                                                                                      ...item,
                                                                                      slots: item.slots.filter(
                                                                                          (
                                                                                              _,
                                                                                              index,
                                                                                          ) =>
                                                                                              index !==
                                                                                              slotIndex,
                                                                                      ),
                                                                                  }
                                                                                : item,
                                                                    ),
                                                                )
                                                            }
                                                        >
                                                            <X />
                                                        </Button>
                                                    </div>
                                                    {locked ? (
                                                        <p className="text-muted-foreground text-xs">
                                                            Booked positions are
                                                            locked until the
                                                            controller
                                                            withdraws.
                                                        </p>
                                                    ) : null}
                                                </div>
                                            );
                                        })}
                                        <Button
                                            type="button"
                                            variant="outline"
                                            className="w-fit"
                                            onClick={() =>
                                                form.setData(
                                                    'shifts',
                                                    form.data.shifts.map(
                                                        (item, index) =>
                                                            index === shiftIndex
                                                                ? {
                                                                      ...item,
                                                                      slots: [
                                                                          ...item.slots,
                                                                          {
                                                                              key:
                                                                                  'new-slot-' +
                                                                                  nextKey.current++,
                                                                              callsign:
                                                                                  '',
                                                                              starts_at:
                                                                                  minimum,
                                                                              ends_at:
                                                                                  maximum,
                                                                          },
                                                                      ],
                                                                  }
                                                                : item,
                                                    ),
                                                )
                                            }
                                        >
                                            <Plus data-icon="inline-start" />
                                            Add position
                                        </Button>
                                    </fieldset>
                                );
                            })}
                            <Button
                                type="button"
                                variant="outline"
                                className="w-fit"
                                disabled={form.processing}
                                onClick={() =>
                                    form.setData('shifts', [
                                        ...form.data.shifts,
                                        {
                                            key:
                                                'new-shift-' +
                                                nextKey.current++,
                                            name: '',
                                            slots: [],
                                        },
                                    ])
                                }
                            >
                                <Plus data-icon="inline-start" />
                                Add shift
                            </Button>
                            <p className="text-muted-foreground text-xs">
                                Each callsign can appear once per shift. Its
                                times must not overlap another shift;
                                consecutive slots may meet at the same time.
                            </p>
                        </div>
                    ) : (
                        <fieldset
                            className="flex flex-col gap-4"
                            disabled={form.processing}
                        >
                            <legend className="mb-3 text-sm font-medium">
                                Available positions
                            </legend>
                            {form.data.positions.map((position, index) => {
                                const locked =
                                    position.id !== undefined &&
                                    interestedPositions.has(position.id);
                                return (
                                    <div
                                        key={position.key}
                                        className="flex flex-col gap-2"
                                    >
                                        <div className="flex items-end gap-3">
                                            <div className="min-w-0 flex-1">
                                                <EventField
                                                    id={position.key}
                                                    label="Position callsign"
                                                    error={
                                                        errors[
                                                            'positions.' +
                                                                index +
                                                                '.callsign'
                                                        ]
                                                    }
                                                >
                                                    <Input
                                                        id={position.key}
                                                        value={
                                                            position.callsign
                                                        }
                                                        placeholder="EKCH_A_TWR"
                                                        className="font-mono"
                                                        required
                                                        maxLength={30}
                                                        disabled={locked}
                                                        onChange={(e) =>
                                                            form.setData(
                                                                'positions',
                                                                form.data.positions.map(
                                                                    (
                                                                        item,
                                                                        itemIndex,
                                                                    ) =>
                                                                        itemIndex ===
                                                                        index
                                                                            ? {
                                                                                  ...item,
                                                                                  callsign:
                                                                                      e.target.value.toUpperCase(),
                                                                              }
                                                                            : item,
                                                                ),
                                                            )
                                                        }
                                                        aria-invalid={
                                                            !!errors[
                                                                'positions.' +
                                                                    index +
                                                                    '.callsign'
                                                            ]
                                                        }
                                                        aria-describedby={
                                                            position.key +
                                                            '-error'
                                                        }
                                                    />
                                                </EventField>
                                            </div>
                                            <Button
                                                type="button"
                                                variant="ghost"
                                                size="icon"
                                                disabled={locked}
                                                aria-label={
                                                    'Remove position ' +
                                                    (position.callsign ||
                                                        index + 1)
                                                }
                                                onClick={() =>
                                                    form.setData(
                                                        'positions',
                                                        form.data.positions.filter(
                                                            (_, itemIndex) =>
                                                                itemIndex !==
                                                                index,
                                                        ),
                                                    )
                                                }
                                            >
                                                <X />
                                            </Button>
                                        </div>
                                        {locked ? (
                                            <p className="text-muted-foreground text-xs">
                                                This position has controller
                                                interest and is locked.
                                            </p>
                                        ) : null}
                                    </div>
                                );
                            })}
                            <Button
                                type="button"
                                variant="outline"
                                className="w-fit"
                                onClick={() =>
                                    form.setData('positions', [
                                        ...form.data.positions,
                                        {
                                            key:
                                                'new-position-' +
                                                nextKey.current++,
                                            callsign: '',
                                        },
                                    ])
                                }
                            >
                                <Plus data-icon="inline-start" />
                                Add position
                            </Button>
                        </fieldset>
                    )}
                    <div className="flex items-start gap-3">
                        <Checkbox
                            id="roster-is-open"
                            checked={form.data.is_open}
                            disabled={form.processing}
                            onCheckedChange={(checked) =>
                                form.setData('is_open', checked === true)
                            }
                        />
                        <div className="flex flex-col gap-1">
                            <Label htmlFor="roster-is-open">
                                Open for controller signups
                            </Label>
                            <p className="text-muted-foreground text-xs">
                                Controllers can view this roster and sign up
                                when it is open. Closing signups preserves
                                existing bookings and interest.
                            </p>
                        </div>
                    </div>
                    <div className="flex flex-wrap gap-3">
                        <Button type="submit" disabled={form.processing}>
                            {form.processing ? (
                                <Spinner data-icon="inline-start" />
                            ) : (
                                <Save data-icon="inline-start" />
                            )}
                            Save roster
                        </Button>
                        {roster ? (
                            <Button
                                type="button"
                                variant="outline"
                                disabled={form.processing}
                                onClick={onClose}
                            >
                                Cancel
                            </Button>
                        ) : null}
                    </div>
                </form>
            </CardContent>
        </Card>
    );
}
