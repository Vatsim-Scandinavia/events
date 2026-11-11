import type { PositionSection } from '@/types/staffing';

/**
 * Pairs each item with the Section heading to show above it: one wherever the Section
 * changes. Sections with nothing shown under them never come up, and a Section without a
 * label at the very top is left out, since a line there would separate nothing.
 */
export function withSectionHeadings<T extends { section: PositionSection | null }>(items: T[]): { item: T; heading: PositionSection | null }[] {
    return items.map((item, index) => {
        const changed = item.section !== null && item.section.id !== items[index - 1]?.section?.id;

        return { item, heading: changed && (item.section!.label !== null || index > 0) ? item.section : null };
    });
}

// A Section heading as a table row: its label, or a thicker line when it has none.
export function SectionTableRow({ section, columns }: { section: PositionSection; columns: number }) {
    if (section.label === null) {
        return (
            <tr aria-hidden="true">
                <td colSpan={columns} className="h-2 bg-muted/60 p-0" />
            </tr>
        );
    }

    return (
        <tr>
            <th
                scope="colgroup"
                colSpan={columns}
                className="bg-muted/40 px-4 py-2 text-left text-xs font-semibold tracking-wide text-muted-foreground uppercase"
            >
                {section.label}
            </th>
        </tr>
    );
}
