import { Plus } from 'lucide-react';
import { useRef, useState, type FormEvent } from 'react';
import { Button } from '@/components/ui/button';
import { FieldError } from '@/components/ui/field';
import { Input } from '@/components/ui/input';
import { CALLSIGN, type DraftProps } from './draft';

// The last row of the timeline: a button that opens inline fields for another position.
// It stays open after adding, so several positions can be typed in one after another.
export function AddPositionRow({ draft, update, newKey }: DraftProps) {
    const [open, setOpen] = useState(draft.positions.length === 0);
    const [callsign, setCallsign] = useState('');
    const [frequency, setFrequency] = useState('');
    const [error, setError] = useState<string | null>(null);
    const callsignRef = useRef<HTMLInputElement>(null);

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
                    onChange={(e) => {
                        setCallsign(e.target.value);
                        setError(null);
                    }}
                    aria-invalid={!!error}
                />
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
