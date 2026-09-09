import { useHttp } from '@inertiajs/react';
import { Search, X } from 'lucide-react';
import { useLayoutEffect, useRef, useState } from 'react';
import { toast } from 'sonner';
import { AirportDialog } from '@/components/airport-dialog';
import { EventField } from '@/components/event-field';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Spinner } from '@/components/ui/spinner';
import { index } from '@/routes/airports';
import type { Airport } from '@/types/events';

export function AirportPicker({
    airports,
    onChange,
    error,
    disabled,
}: {
    airports: Airport[];
    onChange: (airports: Airport[]) => void;
    error?: string;
    disabled: boolean;
}) {
    const lookup = useHttp<{ icao: string }, { airport: Airport | null }>({
        icao: '',
    });
    const [unknownCode, setUnknownCode] = useState<string | null>(null);
    const input = useRef<HTMLInputElement>(null);
    const selection = useRef({ airports, onChange });
    useLayoutEffect(() => {
        selection.current = { airports, onChange };
    }, [airports, onChange]);
    const add = (airport: Airport) => {
        const { airports: selectedAirports, onChange: updateSelection } =
            selection.current;
        if (!selectedAirports.some((selected) => selected.id === airport.id))
            updateSelection([...selectedAirports, airport]);
        lookup.resetAndClearErrors();
    };
    function findAirport() {
        const code = lookup.data.icao.trim().toUpperCase();
        if (!/^[A-Z]{4}$/.test(code)) {
            lookup.setError('icao', 'Enter exactly four letters.');
            return;
        }
        void lookup
            .get(index.url({ query: { icao: code } }), {
                onSuccess: ({ airport }) => {
                    if (airport) add(airport);
                    else setUnknownCode(code);
                },
                onHttpException: () => {
                    toast.error('Airport lookup failed. Please try again.');
                },
                onNetworkError: () => {
                    toast.error('Connection lost. Please try again.');
                },
            })
            .catch(() => {});
    }
    return (
        <>
            <EventField
                id="airport-lookup"
                label="Participating airports"
                error={error ?? lookup.errors.icao}
                hint="Enter an ICAO code. Unknown airports can be added without leaving this form."
            >
                <div className="flex gap-2">
                    <Input
                        ref={input}
                        id="airport-lookup"
                        value={lookup.data.icao}
                        placeholder="EKCH"
                        maxLength={4}
                        autoComplete="off"
                        disabled={disabled || lookup.processing}
                        aria-invalid={!!error || !!lookup.errors.icao}
                        aria-describedby="airport-lookup-hint airport-lookup-error"
                        onChange={(event) => {
                            lookup.setData(
                                'icao',
                                event.target.value.toUpperCase(),
                            );
                            lookup.clearErrors();
                        }}
                        onKeyDown={(event) => {
                            if (event.key === 'Enter') {
                                event.preventDefault();
                                findAirport();
                            }
                        }}
                    />
                    <Button
                        type="button"
                        variant="outline"
                        disabled={
                            disabled || lookup.processing || !lookup.data.icao
                        }
                        onClick={findAirport}
                    >
                        {lookup.processing ? (
                            <Spinner />
                        ) : (
                            <Search data-icon="inline-start" />
                        )}
                        Add
                    </Button>
                </div>
            </EventField>
            <ul
                className="flex flex-col gap-2"
                aria-label="Selected airports"
                aria-live="polite"
            >
                {airports.map((airport) => (
                    <li
                        key={airport.id}
                        className="flex items-center justify-between gap-3 rounded-md border px-3 py-2"
                    >
                        <div className="min-w-0">
                            <span className="font-mono text-sm font-semibold">
                                {airport.icao}
                            </span>
                            <p className="text-muted-foreground truncate text-sm">
                                {airport.name}
                            </p>
                        </div>
                        <Button
                            type="button"
                            variant="ghost"
                            size="icon"
                            aria-label={'Remove ' + airport.icao}
                            disabled={disabled}
                            onClick={() =>
                                onChange(
                                    airports.filter(
                                        (item) => item.id !== airport.id,
                                    ),
                                )
                            }
                        >
                            <X />
                        </Button>
                    </li>
                ))}
            </ul>
            {unknownCode !== null ? (
                <AirportDialog
                    icao={unknownCode}
                    onClose={() => setUnknownCode(null)}
                    onCreated={add}
                    returnFocus={() => input.current?.focus()}
                />
            ) : null}
        </>
    );
}
