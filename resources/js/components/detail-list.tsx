import type { ReactNode } from 'react';

/**
 * Read-only details as a two-column list: short labels on the left, values on the right, so they
 * can be scanned down like a table.
 */
export function DetailList({ details }: { details: [string, ReactNode][] }) {
    return (
        <dl className="grid grid-cols-[auto_minmax(0,1fr)] gap-x-8 gap-y-2 text-sm">
            {details.map(([label, value]) => (
                <div key={label} className="contents">
                    <dt className="text-muted-foreground">{label}</dt>
                    <dd className="truncate tabular-nums">{value}</dd>
                </div>
            ))}
        </dl>
    );
}
