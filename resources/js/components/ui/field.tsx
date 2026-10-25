import { Children, cloneElement, isValidElement, useId, type ReactNode } from 'react';
import { Label } from '@/components/ui/label';
import { cn } from '@/lib/utils';

/**
 * A labelled form control with its validation error underneath.
 */
export function Field({ label, error, children, className }: { label: string; error?: string; children: ReactNode; className?: string }) {
    const errorId = useId();

    // Point the control at its error, so screen readers read it out with the field.
    const controls = error
        ? Children.map(children, (child) =>
              isValidElement<{ 'aria-describedby'?: string }>(child) && child.type !== 'datalist'
                  ? cloneElement(child, { 'aria-describedby': errorId })
                  : child,
          )
        : children;

    return (
        <div className={cn('flex flex-col gap-1.5', className)}>
            <Label className="flex-col items-stretch gap-1.5">
                <span>{label}</span>
                {controls}
            </Label>
            <FieldError id={errorId} message={error} />
        </div>
    );
}

export function FieldError({ id, message }: { id?: string; message?: string }) {
    return message ? (
        <p id={id} className="text-sm text-destructive">
            {message}
        </p>
    ) : null;
}
