import type { ReactNode } from 'react';

/**
 * The heading of a page section, with an optional action such as a "View all" link.
 * On wider screens the action sits on the title's line, aligned to its baseline; on phones it goes below the description.
 */
export default function SectionHeader({ title, description, action }: { title: ReactNode; description: string; action?: ReactNode }) {
    return (
        <div className="mb-8 flex flex-col items-start gap-4 sm:flex-row sm:items-baseline sm:justify-between">
            <div className="flex flex-col gap-2">
                <h2 className="flex items-center gap-3 text-2xl font-semibold tracking-tight">{title}</h2>
                <p className="text-muted-foreground">{description}</p>
            </div>
            {action}
        </div>
    );
}
