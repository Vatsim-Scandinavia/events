import type { ReactNode } from 'react';

/**
 * The heading of a section on the event page: an icon and title, an optional action on the same line,
 * and an optional description 8px below. The section sets the space down to its content.
 */
export default function SectionHeading({
    id,
    icon,
    action,
    description,
    children,
}: {
    id: string;
    icon: ReactNode;
    action?: ReactNode;
    description?: ReactNode;
    children: ReactNode;
}) {
    return (
        <div className="flex flex-col gap-2">
            <div className="flex flex-wrap items-center justify-between gap-4">
                <h2 id={id} className="flex items-center gap-2 text-xl font-semibold tracking-tight">
                    <span className="text-muted-foreground">{icon}</span>
                    {children}
                </h2>
                {action}
            </div>
            {description && <p className="text-sm text-muted-foreground">{description}</p>}
        </div>
    );
}
