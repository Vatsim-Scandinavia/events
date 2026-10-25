import { CalendarDays } from 'lucide-react';
import { useState } from 'react';
import { Button } from '@/components/ui/button';
import { Calendar } from '@/components/ui/calendar';
import { NativeSelect } from '@/components/ui/native-select';
import { Popover, PopoverContent, PopoverTrigger } from '@/components/ui/popover';

const HOUR = 3_600_000;

type Props = {
    // The occurrence start the moment is measured back from.
    startsAt: string;
    // The chosen moment, as whole hours before the start.
    hours: number;
    onChange: (hours: number) => void;
    min: number;
    max: number;
    id?: string;
};

function describe(hours: number): string {
    const days = Math.floor(hours / 24);
    const rest = hours % 24;
    const parts = [days && `${days} day${days === 1 ? '' : 's'}`, rest && `${rest} hour${rest === 1 ? '' : 's'}`].filter(Boolean);

    return parts.length ? `${parts.join(' ')} before the start` : 'when the event starts';
}

/**
 * Picks a date and time (UTC) before an occurrence starts, stored as hours before the
 * start so it carries over to every date of the event. Times keep the start's minutes,
 * so the result is always a whole number of hours.
 */
export default function BeforeStartPicker({ startsAt, hours, onChange, min, max, id }: Props) {
    const [open, setOpen] = useState(false);
    const start = new Date(startsAt).getTime();
    const moment = new Date(start - hours * HOUR);
    const toHours = (time: number) => Math.round((start - time) / HOUR);
    const within = (value: number) => value >= min && value <= max;

    const at = (day: Date, hour: number) => Date.UTC(day.getUTCFullYear(), day.getUTCMonth(), day.getUTCDate(), hour, moment.getUTCMinutes());

    // On a new day keep the time of day, or the nearest allowed hour on that day.
    const pickDay = (day: Date | undefined) => {
        if (!day) {
            return;
        }

        const candidates = Array.from({ length: 24 }, (_, hour) => toHours(at(day, hour))).filter(within);
        const same = toHours(at(day, moment.getUTCHours()));

        if (candidates.length) {
            onChange(within(same) ? same : candidates.reduce((best, value) => (Math.abs(value - same) < Math.abs(best - same) ? value : best)));
        }
    };

    const label = `${moment.toLocaleDateString('en-GB', { weekday: 'short', day: 'numeric', month: 'short', year: 'numeric', timeZone: 'UTC' })}, ${moment.toISOString().slice(11, 16)}z`;

    return (
        <Popover open={open} onOpenChange={setOpen}>
            <PopoverTrigger asChild>
                <Button id={id} variant="outline" className="h-auto w-full justify-start py-1.5 text-left font-normal">
                    <CalendarDays className="text-muted-foreground" />
                    <span className="flex flex-col">
                        <span>{label}</span>
                        <span className="text-xs text-muted-foreground">{describe(hours)}</span>
                    </span>
                </Button>
            </PopoverTrigger>
            <PopoverContent className="flex flex-col gap-3">
                <Calendar
                    mode="single"
                    timeZone="UTC"
                    weekStartsOn={1}
                    selected={moment}
                    defaultMonth={moment}
                    onSelect={pickDay}
                    disabled={(day) => !Array.from({ length: 24 }, (_, hour) => toHours(at(day, hour))).some(within)}
                />
                <NativeSelect
                    aria-label="Time (UTC)"
                    value={moment.getUTCHours()}
                    onChange={(e) => onChange(toHours(at(moment, Number(e.target.value))))}
                >
                    {Array.from({ length: 24 }, (_, hour) => (
                        <option key={hour} value={hour} disabled={!within(toHours(at(moment, hour)))}>
                            {`${String(hour).padStart(2, '0')}:${String(moment.getUTCMinutes()).padStart(2, '0')}z`}
                        </option>
                    ))}
                </NativeSelect>
                <Button size="sm" onClick={() => setOpen(false)}>
                    Done
                </Button>
            </PopoverContent>
        </Popover>
    );
}
