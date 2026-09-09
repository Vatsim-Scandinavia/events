import { useForm } from '@inertiajs/react';
import { Plus, X } from 'lucide-react';
import { useRef } from 'react';
import { toast } from 'sonner';
import AlertError from '@/components/alert-error';
import InputError from '@/components/input-error';
import { RosterTimeFields } from '@/components/roster-time-fields';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { destroy, update } from '@/routes/roster/interest';
import type { Occurrence } from '@/types/events';
import type { EventRoster, RosterInterest } from '@/types/rosters';

export function RosterInterestForm({
    roster,
    occurrence,
    interest,
    canSubmit,
    autoSelectOccurrence,
}: {
    roster: EventRoster;
    occurrence: Occurrence;
    interest: RosterInterest | undefined;
    canSubmit: boolean;
    autoSelectOccurrence: boolean;
}) {
    const nextKey = useRef(0);
    const minimum = occurrence.starts_at?.slice(0, 16) ?? '';
    const maximum = occurrence.ends_at?.slice(0, 16) ?? '';
    const form = useForm({
        occurrence_date: occurrence.date,
        ...(autoSelectOccurrence ? { return_to_current: true } : {}),
        position_ids: interest?.position_ids ?? ([] as number[]),
        availability: (
            interest?.availability ?? [{ starts_at: minimum, ends_at: maximum }]
        ).map((window, index) => ({
            key: 'availability-' + index,
            starts_at: window.starts_at.slice(0, 16),
            ends_at: window.ends_at.slice(0, 16),
        })),
    });
    const withdrawal = useForm({
        occurrence_date: occurrence.date,
        ...(autoSelectOccurrence ? { return_to_current: true } : {}),
    });
    const errors: Record<string, string | undefined> = form.errors;
    const disabled = form.processing || withdrawal.processing || !canSubmit;

    return (
        <Card>
            <CardHeader>
                <CardTitle>Your interest and availability</CardTitle>
                <CardDescription>
                    Choose the positions you can staff and when you are
                    available for this occurrence. Your interest applies only to
                    the displayed date.
                </CardDescription>
            </CardHeader>
            <CardContent>
                <form
                    className="flex flex-col gap-6"
                    onSubmit={(e) => {
                        e.preventDefault();
                        form.transform((data) => ({
                            occurrence_date: data.occurrence_date,
                            ...(data.return_to_current
                                ? { return_to_current: true }
                                : {}),
                            position_ids: data.position_ids,
                            availability: data.availability.map(
                                ({ starts_at, ends_at }) => ({
                                    starts_at,
                                    ends_at,
                                }),
                            ),
                        }));
                        form.submit(update(roster.id), {
                            preserveScroll: true,
                            onSuccess: () => toast.success('Interest saved.'),
                        });
                    }}
                >
                    {form.hasErrors ? (
                        <AlertError
                            title="Check your interest submission"
                            errors={Object.values(form.errors)}
                        />
                    ) : null}
                    {withdrawal.hasErrors ? (
                        <AlertError errors={Object.values(withdrawal.errors)} />
                    ) : null}
                    <fieldset
                        className="flex flex-col gap-3"
                        disabled={disabled}
                    >
                        <legend className="mb-3 text-sm font-medium">
                            Positions you are interested in
                        </legend>
                        <div className="flex flex-wrap gap-x-6 gap-y-3">
                            {!canSubmit && interest?.position_callsigns?.length
                                ? interest.position_callsigns.map(
                                      (callsign) => (
                                          <Badge
                                              key={callsign}
                                              variant="outline"
                                          >
                                              {callsign}
                                          </Badge>
                                      ),
                                  )
                                : roster.positions.map((position) => (
                                      <div
                                          key={position.id}
                                          className="flex items-center gap-2"
                                      >
                                          <Checkbox
                                              id={
                                                  'interest-position-' +
                                                  position.id
                                              }
                                              checked={form.data.position_ids.includes(
                                                  position.id,
                                              )}
                                              onCheckedChange={(checked) =>
                                                  form.setData(
                                                      'position_ids',
                                                      checked === true
                                                          ? [
                                                                ...form.data
                                                                    .position_ids,
                                                                position.id,
                                                            ]
                                                          : form.data.position_ids.filter(
                                                                (id) =>
                                                                    id !==
                                                                    position.id,
                                                            ),
                                                  )
                                              }
                                              aria-invalid={
                                                  !!form.errors.position_ids
                                              }
                                              aria-describedby="interest-positions-error"
                                          />
                                          <Label
                                              htmlFor={
                                                  'interest-position-' +
                                                  position.id
                                              }
                                              className="font-mono"
                                          >
                                              {position.callsign}
                                          </Label>
                                      </div>
                                  ))}
                        </div>
                        <InputError
                            id="interest-positions-error"
                            message={form.errors.position_ids}
                        />
                    </fieldset>
                    <fieldset
                        className="flex flex-col gap-4"
                        disabled={disabled}
                    >
                        <legend className="mb-3 text-sm font-medium">
                            Availability in UTC
                        </legend>
                        {form.data.availability.map((window, index) => (
                            <div
                                key={window.key}
                                className="grid min-w-0 items-start gap-3 md:grid-cols-[minmax(0,1fr)_minmax(0,1fr)_auto]"
                            >
                                <RosterTimeFields
                                    id={window.key}
                                    value={window}
                                    minimum={minimum}
                                    maximum={maximum}
                                    disabled={disabled}
                                    errors={{
                                        starts_at:
                                            errors[
                                                'availability.' +
                                                    index +
                                                    '.starts_at'
                                            ],
                                        ends_at:
                                            errors[
                                                'availability.' +
                                                    index +
                                                    '.ends_at'
                                            ],
                                    }}
                                    onChange={(field, value) =>
                                        form.setData(
                                            'availability',
                                            form.data.availability.map(
                                                (item, itemIndex) =>
                                                    itemIndex === index
                                                        ? {
                                                              ...item,
                                                              [field]: value,
                                                          }
                                                        : item,
                                            ),
                                        )
                                    }
                                />
                                <Button
                                    type="button"
                                    variant="ghost"
                                    size="icon"
                                    className="self-end"
                                    disabled={
                                        disabled ||
                                        form.data.availability.length === 1
                                    }
                                    aria-label={
                                        'Remove availability window ' +
                                        (index + 1)
                                    }
                                    onClick={() =>
                                        form.setData(
                                            'availability',
                                            form.data.availability.filter(
                                                (_, itemIndex) =>
                                                    itemIndex !== index,
                                            ),
                                        )
                                    }
                                >
                                    <X />
                                </Button>
                            </div>
                        ))}
                        <InputError message={form.errors.availability} />
                        <Button
                            type="button"
                            variant="outline"
                            className="w-fit"
                            onClick={() =>
                                form.setData('availability', [
                                    ...form.data.availability,
                                    {
                                        key:
                                            'new-availability-' +
                                            nextKey.current++,
                                        starts_at: minimum,
                                        ends_at: maximum,
                                    },
                                ])
                            }
                        >
                            <Plus data-icon="inline-start" />
                            Add availability window
                        </Button>
                    </fieldset>
                    <div className="flex flex-wrap gap-3">
                        {canSubmit ? (
                            <Button type="submit" disabled={disabled}>
                                {form.processing ? (
                                    <Spinner data-icon="inline-start" />
                                ) : null}
                                {interest
                                    ? 'Update interest'
                                    : 'Submit interest'}
                            </Button>
                        ) : null}
                        {interest ? (
                            <Button
                                type="button"
                                variant="outline"
                                disabled={
                                    form.processing || withdrawal.processing
                                }
                                onClick={() =>
                                    withdrawal.submit(destroy(roster.id), {
                                        preserveScroll: true,
                                        onSuccess: () =>
                                            toast.success(
                                                'Interest withdrawn.',
                                            ),
                                    })
                                }
                            >
                                {withdrawal.processing ? (
                                    <Spinner data-icon="inline-start" />
                                ) : null}
                                Withdraw interest
                            </Button>
                        ) : null}
                    </div>
                </form>
            </CardContent>
        </Card>
    );
}
