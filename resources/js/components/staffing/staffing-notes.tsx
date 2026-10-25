import { TriangleAlert } from 'lucide-react';
import { cn } from '@/lib/utils';

/**
 * Notes from Integrations on a booking or roster entry, such as a missing endorsement: a warning
 * icon with the notes as its tooltip, read out in full by screen readers. Never a blocker.
 */
export function NotesIcon({ notes, className }: { notes: string[]; className?: string }) {
    if (notes.length === 0) {
        return null;
    }

    return (
        <span title={notes.join('\n')} className={cn('inline-flex shrink-0 align-middle text-warning', className)}>
            <TriangleAlert className="size-3.5" aria-hidden />
            <span className="sr-only">{notes.join(' ')}</span>
        </span>
    );
}

/**
 * What the signed-in controller should know before working a position.
 */
export function PositionNote({ note }: { note: string | null | undefined }) {
    return note ? (
        <span className="mt-0.5 flex items-start gap-1 text-xs font-normal text-warning">
            <TriangleAlert className="mt-px size-3 shrink-0" aria-hidden />
            {note}
        </span>
    ) : null;
}
