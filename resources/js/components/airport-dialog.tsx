import { useHttp } from '@inertiajs/react';
import { toast } from 'sonner';
import { EventField } from '@/components/event-field';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Spinner } from '@/components/ui/spinner';
import { store } from '@/routes/airports';
import type { Airport } from '@/types/events';

export function AirportDialog({
    icao = '',
    onClose,
    onCreated,
    returnFocus,
}: {
    icao?: string;
    onClose: () => void;
    onCreated: (airport: Airport) => void;
    returnFocus: () => void;
}) {
    const form = useHttp<
        { icao: string; name: string; country: string },
        { airport: Airport }
    >({ icao, name: '', country: '' });
    return (
        <Dialog
            open
            onOpenChange={(open) => {
                if (!open && !form.processing) onClose();
            }}
        >
            <DialogContent
                className="max-h-[90dvh] overflow-y-auto"
                onCloseAutoFocus={(event) => {
                    event.preventDefault();
                    returnFocus();
                }}
            >
                <DialogHeader>
                    <DialogTitle>Add airport</DialogTitle>
                    <DialogDescription>
                        {icao
                            ? icao +
                              ' is not in the directory yet. Add its details to use it in this event.'
                            : 'Add an airport to the shared directory using its ICAO code.'}
                    </DialogDescription>
                </DialogHeader>
                <form
                    className="flex flex-col gap-5"
                    onSubmit={(event) => {
                        event.preventDefault();
                        event.stopPropagation();
                        void form
                            .post(store.url(), {
                                onSuccess: ({ airport }) => {
                                    toast.success(airport.icao + ' added.');
                                    onCreated(airport);
                                    onClose();
                                },
                                onHttpException: () => {
                                    toast.error(
                                        'The airport could not be saved. Please try again.',
                                    );
                                },
                                onNetworkError: () => {
                                    toast.error(
                                        'Connection lost. Please try again.',
                                    );
                                },
                            })
                            .catch(() => {});
                    }}
                >
                    <EventField
                        id="airport-icao"
                        label="ICAO code"
                        error={form.errors.icao}
                    >
                        <Input
                            id="airport-icao"
                            value={form.data.icao}
                            onChange={(event) =>
                                form.setData(
                                    'icao',
                                    event.target.value.toUpperCase(),
                                )
                            }
                            required
                            maxLength={4}
                            pattern="[A-Z]{4}"
                            autoComplete="off"
                            disabled={form.processing}
                            aria-invalid={!!form.errors.icao}
                            aria-describedby="airport-icao-error"
                        />
                    </EventField>
                    {(['name', 'country'] as const).map((field) => (
                        <EventField
                            key={field}
                            id={'airport-' + field}
                            label={
                                field === 'name' ? 'Airport name' : 'Country'
                            }
                            error={form.errors[field]}
                        >
                            <Input
                                id={'airport-' + field}
                                value={form.data[field]}
                                onChange={(event) =>
                                    form.setData(field, event.target.value)
                                }
                                required
                                maxLength={255}
                                disabled={form.processing}
                                aria-invalid={!!form.errors[field]}
                                aria-describedby={'airport-' + field + '-error'}
                            />
                        </EventField>
                    ))}
                    <DialogFooter>
                        <Button
                            type="button"
                            variant="outline"
                            disabled={form.processing}
                            onClick={onClose}
                        >
                            Cancel
                        </Button>
                        <Button type="submit" disabled={form.processing}>
                            {form.processing ? <Spinner /> : null}Add airport
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}
