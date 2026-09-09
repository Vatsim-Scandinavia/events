import { EventField } from '@/components/event-field';
import { Input } from '@/components/ui/input';
import type { RosterAvailability } from '@/types/rosters';

export function RosterTimeFields({
    id,
    value,
    onChange,
    minimum,
    maximum,
    disabled,
    errors = {},
}: {
    id: string;
    value: RosterAvailability;
    onChange: (field: keyof RosterAvailability, value: string) => void;
    minimum: string;
    maximum: string;
    disabled: boolean;
    errors?: Partial<Record<keyof RosterAvailability, string>>;
}) {
    return (
        <>
            {(['starts_at', 'ends_at'] as const).map((field) => (
                <EventField
                    key={field}
                    id={id + '-' + field}
                    label={field === 'starts_at' ? 'From (UTC)' : 'Until (UTC)'}
                    error={errors[field]}
                >
                    <Input
                        id={id + '-' + field}
                        type="datetime-local"
                        value={value[field]}
                        onChange={(e) => onChange(field, e.target.value)}
                        min={minimum}
                        max={maximum}
                        required
                        disabled={disabled}
                        aria-invalid={!!errors[field]}
                        aria-describedby={id + '-' + field + '-error'}
                    />
                </EventField>
            ))}
        </>
    );
}
