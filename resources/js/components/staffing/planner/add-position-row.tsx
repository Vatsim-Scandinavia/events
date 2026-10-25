import { usePage } from '@inertiajs/react';
import { Plus } from 'lucide-react';
import { useId, useMemo, useRef, useState, type FormEvent } from 'react';
import { Button } from '@/components/ui/button';
import { FieldError } from '@/components/ui/field';
import { Input } from '@/components/ui/input';
import type { CataloguePosition } from '@/types/staffing';
import { CALLSIGN, type DraftPosition, type DraftProps, type StaffingPageProps } from './draft';

// The last row of the timeline: a button that opens inline fields for another position.
// It stays open after adding, so several positions can be typed in one after another.
// Positions in the catalogue are suggested as you type, and picking one fills in its frequency.
export function AddPositionRow({ draft, update, newKey }: DraftProps) {
    const { catalogue } = usePage<StaffingPageProps>().props;
    const suggestions = useId();
    const [open, setOpen] = useState(draft.positions.length === 0);
    const [callsign, setCallsign] = useState('');
    const [frequency, setFrequency] = useState('');
    const [error, setError] = useState<string | null>(null);
    const callsignRef = useRef<HTMLInputElement>(null);
    const matches = useMemo(() => suggest(catalogue, callsign, draft.positions), [catalogue, callsign, draft.positions]);
    const filledFrequency = useRef<string | null>(null);

    const close = () => {
        setOpen(false);
        setCallsign('');
        setFrequency('');
        setError(null);
    };

    const add = (e: FormEvent) => {
        e.preventDefault();
        const value = callsign.trim().toUpperCase();

        if (!CALLSIGN.test(value)) {
            return setError('Use a callsign like EKCH_TWR.');
        }

        if (draft.positions.some((position) => position.callsign === value)) {
            return setError(`${value} is already on the timeline. A position can have as many shifts as you need.`);
        }

        if (frequency.trim() && !/^1[1-3]\d\.\d{2,3}$/.test(frequency.trim())) {
            return setError('Use a frequency like 118.105.');
        }

        update((current) => ({
            ...current,
            positions: [...current.positions, { key: newKey(), callsign: value, frequency: frequency.trim() || null }],
        }));
        setCallsign('');
        setFrequency('');
        setError(null);
        callsignRef.current?.focus();
    };

    if (!open) {
        return (
            <div className="py-1.5">
                <Button variant="ghost" size="sm" onClick={() => setOpen(true)}>
                    <Plus />
                    Add position
                </Button>
            </div>
        );
    }

    return (
        <form onSubmit={add} onKeyDown={(e) => e.key === 'Escape' && close()} className="flex flex-col gap-1.5 py-2">
            <div className="flex flex-wrap items-center gap-2">
                <Input
                    ref={callsignRef}
                    autoFocus
                    aria-label="Callsign"
                    placeholder="Callsign, e.g. EKCH_TWR"
                    className="w-48 font-mono uppercase"
                    value={callsign}
                    list={catalogue.length > 0 ? suggestions : undefined}
                    onChange={(e) => {
                        const known = catalogue.find((position) => position.callsign === e.target.value.trim().toUpperCase());

                        setCallsign(e.target.value);
                        setError(null);

                        // Fill in a known frequency, but never over one typed by hand.
                        if (known?.frequency && (!frequency.trim() || frequency === filledFrequency.current)) {
                            setFrequency(known.frequency);
                            filledFrequency.current = known.frequency;
                        }
                    }}
                    aria-invalid={!!error}
                />
                {catalogue.length > 0 && (
                    <datalist id={suggestions}>
                        {matches.map((position) => (
                            <option key={position.callsign} value={position.callsign}>
                                {position.name ?? undefined}
                            </option>
                        ))}
                    </datalist>
                )}
                <Input
                    aria-label="Frequency (optional)"
                    placeholder="Frequency (optional)"
                    className="w-40 font-mono"
                    value={frequency}
                    onChange={(e) => setFrequency(e.target.value)}
                />
                <Button type="submit" size="sm">
                    <Plus />
                    Add
                </Button>
                {draft.positions.length > 0 && (
                    <Button type="button" size="sm" variant="ghost" onClick={close}>
                        Done
                    </Button>
                )}
            </div>
            <FieldError message={error ?? undefined} />
        </form>
    );
}

// Browsers redraw a datalist on every keystroke, which gets slow with hundreds of positions, so
// offer only the best few: callsigns starting with what's typed, then other callsign or name
// matches. Nothing until two characters are typed, or once it's a position already planned.
const MAX_SUGGESTIONS = 20;

function suggest(catalogue: CataloguePosition[], typed: string, planned: DraftPosition[]): CataloguePosition[] {
    const query = typed.trim().toUpperCase();

    if (query.length < 2) {
        return [];
    }

    const available = catalogue.filter((position) => !planned.some((item) => item.callsign === position.callsign));
    const starting = available.filter((position) => position.callsign.startsWith(query));
    const containing = available.filter(
        (position) => !position.callsign.startsWith(query) && (position.callsign.includes(query) || position.name?.toUpperCase().includes(query)),
    );

    return [...starting, ...containing].slice(0, MAX_SUGGESTIONS);
}
