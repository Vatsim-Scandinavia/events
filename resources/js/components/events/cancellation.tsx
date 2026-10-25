import { router } from '@inertiajs/react';
import { Ban, RotateCcw } from 'lucide-react';
import { useConfirm } from '@/components/confirm-dialog';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { formatDay } from '@/lib/repeat';
import { cn } from '@/lib/utils';
import type { EditableDate, EditableEvent } from '@/types/event';

/**
 * Calling off the whole event or single dates. These apply straight away, apart from
 * the form, and keep the dates' staffing so they can be restored.
 */
export function Cancellation({ event }: { event: EditableEvent }) {
    const confirm = useConfirm();
    const upcoming = event.dates.filter((date) => !date.hasStarted);
    const isSeries = event.dates.length > 1;
    const noun = isSeries ? 'series' : 'event';

    if (upcoming.length === 0 && !event.cancelled) {
        return null;
    }

    const cancelEvent = async () => {
        const confirmed = await confirm({
            title: `Cancel ${event.name}?`,
            description: `${isSeries ? 'All upcoming dates' : 'The event'} will show as cancelled and drop off the events list. Controllers can no longer book or show interest. Staffing is kept, so you can restore the ${noun} later.`,
            confirmLabel: `Cancel ${noun}`,
            cancelLabel: 'Keep it',
            destructive: true,
        });

        if (confirmed) {
            router.post(`/events/${event.id}/cancel`, {}, { preserveScroll: true });
        }
    };

    const cancelDate = async (date: EditableDate) => {
        const confirmed = await confirm({
            title: `Cancel ${formatDay(date.date)}?`,
            description:
                'This date will show as cancelled, and controllers can no longer book or show interest for it. Its staffing is kept, so you can restore it later.',
            confirmLabel: 'Cancel date',
            cancelLabel: 'Keep it',
            destructive: true,
        });

        if (confirmed) {
            router.post(`/occurrences/${date.id}/cancel`, {}, { preserveScroll: true });
        }
    };

    return (
        <Card className="ring-destructive/40">
            <CardHeader>
                <CardTitle>Cancel</CardTitle>
                <CardDescription>
                    {event.cancelled
                        ? `This ${noun} is cancelled. Restore it to put its dates back on.`
                        : isSeries
                          ? 'Call off a single date or the whole series. Cancelling applies straight away and can be undone.'
                          : 'Call off the event. Cancelling applies straight away and can be undone.'}
                </CardDescription>
            </CardHeader>
            <CardContent className="flex flex-col gap-4">
                {isSeries && !event.cancelled && upcoming.length > 0 && (
                    <ul className="flex max-h-72 flex-col overflow-y-auto rounded-lg ring-1 ring-foreground/10">
                        {upcoming.map((date) => (
                            <li key={date.id} className="flex items-center justify-between gap-3 border-b px-3 py-2 text-sm last:border-b-0">
                                <span className={cn('tabular-nums', date.cancelled && 'text-muted-foreground line-through')}>
                                    {formatDay(date.date)} · {date.time}
                                </span>
                                {date.cancelled ? (
                                    <Button
                                        type="button"
                                        variant="ghost"
                                        size="sm"
                                        onClick={() => router.delete(`/occurrences/${date.id}/cancel`, { preserveScroll: true })}
                                    >
                                        <RotateCcw />
                                        Restore
                                    </Button>
                                ) : (
                                    <Button
                                        type="button"
                                        variant="ghost"
                                        size="sm"
                                        className="text-destructive hover:bg-destructive/10 hover:text-destructive"
                                        onClick={() => cancelDate(date)}
                                    >
                                        <Ban />
                                        Cancel date
                                    </Button>
                                )}
                            </li>
                        ))}
                    </ul>
                )}
                {event.cancelled ? (
                    <Button
                        type="button"
                        variant="outline"
                        className="self-start"
                        onClick={() => router.delete(`/events/${event.id}/cancel`, { preserveScroll: true })}
                    >
                        <RotateCcw />
                        Restore {noun}
                    </Button>
                ) : (
                    <Button type="button" variant="destructive" className="self-start" onClick={cancelEvent}>
                        <Ban />
                        Cancel {noun}
                    </Button>
                )}
            </CardContent>
        </Card>
    );
}
