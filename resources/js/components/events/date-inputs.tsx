import { CalendarDays, Lock } from 'lucide-react';
import { useState } from 'react';
import { Button } from '@/components/ui/button';
import { Calendar } from '@/components/ui/calendar';
import { NativeSelect } from '@/components/ui/native-select';
import { Popover, PopoverContent, PopoverTrigger } from '@/components/ui/popover';
import { formatDay, parseDay, toDay } from '@/lib/repeat';

function TimeSelect({ value, onChange, label }: { value: string; onChange: (value: string) => void; label: string }) {
    const [hours, minutes] = value.split(':');
    const pad = (number: number) => String(number).padStart(2, '0');
    // Keep a minute that isn't on the 5-minute grid selectable.
    const minuteOptions = [...new Set([...Array.from({ length: 12 }, (_, index) => pad(index * 5)), minutes])].sort();

    return (
        <div className="flex items-center gap-1.5">
            <NativeSelect aria-label={`${label}, hour`} value={hours} onChange={(e) => onChange(`${e.target.value}:${minutes}`)}>
                {Array.from({ length: 24 }, (_, hour) => (
                    <option key={hour} value={pad(hour)}>
                        {pad(hour)}
                    </option>
                ))}
            </NativeSelect>
            <span className="text-muted-foreground">:</span>
            <NativeSelect aria-label={`${label}, minutes`} value={minutes} onChange={(e) => onChange(`${hours}:${e.target.value}`)}>
                {minuteOptions.map((minute) => (
                    <option key={minute} value={minute}>
                        {minute}
                    </option>
                ))}
            </NativeSelect>
            <span className="text-sm text-muted-foreground">z</span>
        </div>
    );
}

export function DatePicker({ value, min, label, onChange }: { value: string; min: string; label: string; onChange: (value: string) => void }) {
    const [open, setOpen] = useState(false);
    const selected = parseDay(value);

    return (
        <Popover open={open} onOpenChange={setOpen}>
            <PopoverTrigger asChild>
                <Button type="button" variant="outline" className="flex-1 justify-start font-normal" aria-label={`${label}: ${formatDay(value)}`}>
                    <CalendarDays className="text-muted-foreground" />
                    {formatDay(value)}
                </Button>
            </PopoverTrigger>
            <PopoverContent className="w-auto">
                <Calendar
                    mode="single"
                    timeZone="UTC"
                    weekStartsOn={1}
                    selected={selected}
                    defaultMonth={selected}
                    required
                    disabled={{ before: parseDay(min) }}
                    onSelect={(day) => {
                        onChange(toDay(day.getTime()));
                        setOpen(false);
                    }}
                />
            </PopoverContent>
        </Popover>
    );
}

/**
 * One field for a date and a time (in the event's time zone): the button shows both, and its popover holds
 * the calendar with the time below it. A locked date still lets the time change.
 */
export function DateTimePicker({
    label,
    date,
    time,
    min,
    dateLocked = false,
    onDateChange,
    onTimeChange,
}: {
    label: string;
    date: string;
    time: string;
    min: string;
    dateLocked?: boolean;
    onDateChange: (date: string) => void;
    onTimeChange: (time: string) => void;
}) {
    const [open, setOpen] = useState(false);
    const selected = parseDay(date);
    const text = `${formatDay(date)}, ${time}z`;

    return (
        <Popover open={open} onOpenChange={setOpen}>
            <PopoverTrigger asChild>
                <Button type="button" variant="outline" className="w-full justify-start font-normal" aria-label={`${label}: ${text}`}>
                    <CalendarDays className="text-muted-foreground" />
                    {text}
                </Button>
            </PopoverTrigger>
            <PopoverContent className="flex w-auto flex-col gap-3">
                {dateLocked ? (
                    <p className="flex max-w-64 items-start gap-2 text-sm text-muted-foreground">
                        <Lock className="mt-0.5 size-4 shrink-0" />
                        The series has started, so its first date stays {formatDay(date)}. The time can still change.
                    </p>
                ) : (
                    <Calendar
                        mode="single"
                        timeZone="UTC"
                        weekStartsOn={1}
                        selected={selected}
                        defaultMonth={selected}
                        required
                        disabled={{ before: parseDay(min) }}
                        onSelect={(day) => onDateChange(toDay(day.getTime()))}
                    />
                )}
                <div className="flex items-center justify-between gap-3">
                    <span className="text-sm text-muted-foreground">Time</span>
                    <TimeSelect value={time} onChange={onTimeChange} label={`${label} time`} />
                </div>
                <Button type="button" size="sm" onClick={() => setOpen(false)}>
                    Done
                </Button>
            </PopoverContent>
        </Popover>
    );
}
