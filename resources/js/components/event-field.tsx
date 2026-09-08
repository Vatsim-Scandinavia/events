import type { ReactNode } from 'react';
import InputError from '@/components/input-error';
import { Label } from '@/components/ui/label';

export function EventField({
    id,
    label,
    error,
    hint,
    children,
}: {
    id: string;
    label: string;
    error?: string;
    hint?: string;
    children: ReactNode;
}) {
    return (
        <div className="flex min-w-0 flex-col gap-2" data-invalid={!!error}>
            <Label htmlFor={id}>{label}</Label>
            {children}
            {hint ? (
                <p id={id + '-hint'} className="text-muted-foreground text-xs">
                    {hint}
                </p>
            ) : null}
            <InputError id={id + '-error'} message={error} />
        </div>
    );
}
