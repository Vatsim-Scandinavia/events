import { useEffect, useRef, useState, type PointerEvent as ReactPointerEvent } from 'react';
import type { TimelineItem } from '@/components/staffing/timeline';

export type Drag = {
    kind: 'create' | 'move' | 'resize-start' | 'resize-end';
    item?: TimelineItem;
    rowId: number;
    origin: number;
    start: number;
    end: number;
    moved: boolean;
};

export const SNAP = 15;

// Whether a span would overlap another item on the same row, other than the one being changed.
export const clashes = (items: TimelineItem[], rowId: number, start: number, end: number, except?: number) =>
    items.some((item) => item.rowId === rowId && item.id !== except && item.start < end && item.end > start);

type Options = {
    noun: string;
    duration: number;
    items: TimelineItem[];
    readOnly: boolean;
    allowRowChange: boolean;
    onCreate?: (rowId: number, start: number, end: number) => void;
    onChange?: (item: TimelineItem, rowId: number, start: number, end: number) => void;
    // Called with a message when a drag ends somewhere it can't, e.g. on top of another item.
    onReject: (message: string) => void;
};

// Drawing, moving and resizing spans with the pointer. Times snap to SNAP minutes, measured
// against the element trackRef points at.
export function useTimelineDrag({ noun, duration, items, readOnly, allowRowChange, onCreate, onChange, onReject }: Options) {
    const trackRef = useRef<HTMLDivElement>(null);
    const [drag, setDrag] = useState<Drag | null>(null);
    const dragRef = useRef<Drag | null>(null);
    dragRef.current = drag;

    const minuteAt = (clientX: number) => {
        const rect = trackRef.current!.getBoundingClientRect();
        const raw = ((clientX - rect.left) / rect.width) * duration;

        return Math.min(duration, Math.max(0, Math.round(raw / SNAP) * SNAP));
    };

    const rowAt = (clientX: number, clientY: number, fallback: number) => {
        const element = document.elementFromPoint(clientX, clientY)?.closest<HTMLElement>('[data-timeline-row]');

        return element ? Number(element.dataset.timelineRow) : fallback;
    };

    useEffect(() => {
        if (!drag) {
            return;
        }

        const move = (event: PointerEvent) => {
            const current = dragRef.current!;
            const minute = minuteAt(event.clientX);
            const next = {
                ...current,
                moved: current.moved || minute !== current.origin,
            };

            if (current.kind === 'create') {
                next.start = Math.min(current.origin, minute);
                next.end = Math.max(current.origin, minute);
            } else if (current.kind === 'move') {
                const item = current.item!;
                const length = item.end - item.start;
                next.start = Math.min(duration - length, Math.max(0, item.start + minute - current.origin));
                next.end = next.start + length;
                next.rowId = allowRowChange ? rowAt(event.clientX, event.clientY, current.rowId) : current.rowId;
                next.moved = next.moved || next.rowId !== item.rowId;
            } else if (current.kind === 'resize-start') {
                next.start = Math.min(current.item!.end - SNAP, minute);
            } else {
                next.end = Math.max(current.item!.start + SNAP, minute);
            }

            setDrag(next);
        };

        const up = () => {
            const current = dragRef.current!;
            setDrag(null);

            // A click on an empty stretch adds an hour, cut short by the next span or the end.
            if (current.kind === 'create' && !current.moved) {
                const next = Math.min(
                    duration,
                    ...items.filter((item) => item.rowId === current.rowId && item.start >= current.origin).map((item) => item.start),
                );
                current.start = Math.min(current.origin, duration - SNAP);
                current.end = Math.min(current.start + 60, next);
                current.moved = true;
            }

            if (!current.moved) {
                return;
            }

            if (current.end - current.start < SNAP || clashes(items, current.rowId, current.start, current.end, current.item?.id)) {
                onReject(
                    current.kind === 'create'
                        ? `There’s no room for a ${noun} there; it would overlap another one.`
                        : `That would overlap another ${noun} on the same position.`,
                );

                return;
            }

            if (current.kind === 'create') {
                onCreate?.(current.rowId, current.start, current.end);
            } else if (current.item) {
                const { item } = current;

                if (item.rowId !== current.rowId || item.start !== current.start || item.end !== current.end) {
                    onChange?.(item, current.rowId, current.start, current.end);
                }
            }
        };

        window.addEventListener('pointermove', move);
        window.addEventListener('pointerup', up, { once: true });

        return () => {
            window.removeEventListener('pointermove', move);
            window.removeEventListener('pointerup', up);
        };
        // The listeners only need re-binding when a drag starts or ends.
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [drag !== null]);

    const begin = (event: ReactPointerEvent, kind: Drag['kind'], rowId: number, item?: TimelineItem) => {
        event.stopPropagation();

        if (readOnly || event.button !== 0 || item?.locked) {
            return;
        }

        event.preventDefault();
        const minute = minuteAt(event.clientX);
        setDrag({
            kind,
            item,
            rowId,
            origin: minute,
            start: item?.start ?? minute,
            end: item?.end ?? minute,
            moved: false,
        });
    };

    return { trackRef, drag, begin, minuteAt };
}
