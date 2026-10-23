import { Lock, Plus, Trash2, X } from 'lucide-react';
import {
    useEffect,
    useId,
    useRef,
    useState,
    type DragEvent,
    type KeyboardEvent as ReactKeyboardEvent,
    type PointerEvent as ReactPointerEvent,
    type ReactNode,
} from 'react';
import { usePage } from '@inertiajs/react';
import { clockIn, zoneSuffix } from '@/lib/time';
import { cn } from '@/lib/utils';

export type TimelineRow = {
    id: number;
    label: string;
    detail?: string | null;
    highlighted?: boolean;
};

export type TimelineItem = {
    id: number;
    rowId: number;
    // Minutes from the start of the timeline.
    start: number;
    end: number;
    label: string;
    locked?: boolean;
    lockedReason?: string;
    tone?: 'default' | 'muted' | 'warning';
};

type Props = {
    // What the items are, for messages: "shift" or "roster entry".
    noun: string;
    startsAt: string;
    duration: number;
    rows: TimelineRow[];
    items: TimelineItem[];
    readOnly?: boolean;
    // Whether items can be dragged from one row to another.
    allowRowChange?: boolean;
    onCreate?: (rowId: number, start: number, end: number) => void;
    onChange?: (item: TimelineItem, rowId: number, start: number, end: number) => void;
    onDelete?: (item: TimelineItem) => void;
    // Something dragged in from outside the timeline (e.g. a controller) dropped on a row.
    onExternalDrop?: (rowId: number, minute: number, data: string) => void;
    // Spans to shade on every row, e.g. the availability windows of the controller being dragged.
    highlight?: { start: number; end: number }[] | null;
    emptyHint?: string;
    // Shown as a remove button on each row's label.
    onRemoveRow?: (rowId: number) => void;
    // Rendered under the last row, e.g. a way to add another row.
    footer?: ReactNode;
};

type Drag = {
    kind: 'create' | 'move' | 'resize-start' | 'resize-end';
    item?: TimelineItem;
    rowId: number;
    origin: number;
    start: number;
    end: number;
    moved: boolean;
};

const SNAP = 15;

// Labels a span of minutes from the start in the viewer's time zone, e.g. "18:00–20:00 CEST".
function useSpanLabel(startsAt: string) {
    const { displayTimezone } = usePage().props;
    const suffix = zoneSuffix(displayTimezone, startsAt);

    return {
        clock: (minute: number) => clockIn(displayTimezone, startsAt, minute),
        span: (start: number, end: number) => `${clockIn(displayTimezone, startsAt, start)}–${clockIn(displayTimezone, startsAt, end)}${suffix}`,
        suffix,
    };
}

/**
 * Positions as rows against the event's hours. Drag on an empty stretch of a row to
 * draw a span, drag a span to move it, and drag its edges to resize it. Times snap to
 * 15 minutes and are shown in the viewer's time zone.
 */
export default function Timeline({
    noun,
    startsAt,
    duration,
    rows,
    items,
    readOnly = false,
    allowRowChange = false,
    onCreate,
    onChange,
    onDelete,
    onExternalDrop,
    highlight,
    emptyHint,
    onRemoveRow,
    footer,
}: Props) {
    const { clock, span: spanLabel, suffix } = useSpanLabel(startsAt);
    const trackRef = useRef<HTMLDivElement>(null);
    const [drag, setDrag] = useState<Drag | null>(null);
    const [dropTarget, setDropTarget] = useState<{
        rowId: number;
        minute: number;
    } | null>(null);
    const [rejected, setRejected] = useState<string | null>(null);
    const dragRef = useRef<Drag | null>(null);
    dragRef.current = drag;
    const hintId = useId();

    useEffect(() => {
        if (!rejected) {
            return;
        }

        const timer = setTimeout(() => setRejected(null), 4000);

        return () => clearTimeout(timer);
    }, [rejected]);

    const minuteAt = (clientX: number) => {
        const rect = trackRef.current!.getBoundingClientRect();
        const raw = ((clientX - rect.left) / rect.width) * duration;

        return Math.min(duration, Math.max(0, Math.round(raw / SNAP) * SNAP));
    };

    const rowAt = (clientX: number, clientY: number, fallback: number) => {
        const element = document.elementFromPoint(clientX, clientY)?.closest<HTMLElement>('[data-timeline-row]');

        return element ? Number(element.dataset.timelineRow) : fallback;
    };

    const clashes = (rowId: number, start: number, end: number, except?: number) =>
        items.some((item) => item.rowId === rowId && item.id !== except && item.start < end && item.end > start);

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

            if (current.end - current.start < SNAP || clashes(current.rowId, current.start, current.end, current.item?.id)) {
                setRejected(
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

    // Keyboard editing: arrows move a span by one snap, Shift+arrows change its length,
    // up and down move it to the next row, and Delete removes it.
    const [focusId, setFocusId] = useState<number | null>(null);

    useEffect(() => {
        if (focusId === null) {
            return;
        }

        document.querySelector<HTMLElement>(`[data-timeline-item="${focusId}"]`)?.focus();
        setFocusId(null);
    }, [focusId, items]);

    const keyboard = (event: ReactKeyboardEvent, item: TimelineItem) => {
        if (readOnly || item.locked) {
            return;
        }

        const length = item.end - item.start;
        const rowIndex = rows.findIndex((row) => row.id === item.rowId);
        let { rowId, start, end } = item;

        switch (event.key) {
            case 'ArrowLeft':
            case 'ArrowRight': {
                const step = event.key === 'ArrowLeft' ? -SNAP : SNAP;

                if (event.shiftKey) {
                    end = Math.min(duration, Math.max(start + SNAP, end + step));
                } else {
                    start = Math.min(duration - length, Math.max(0, start + step));
                    end = start + length;
                }

                break;
            }
            case 'ArrowUp':
            case 'ArrowDown': {
                const target = rows[rowIndex + (event.key === 'ArrowUp' ? -1 : 1)];

                if (!allowRowChange || !target) {
                    return;
                }

                rowId = target.id;
                break;
            }
            case 'Delete':
            case 'Backspace':
                event.preventDefault();
                onDelete?.(item);

                return;
            default:
                return;
        }

        event.preventDefault();

        if (rowId === item.rowId && start === item.start && end === item.end) {
            return;
        }

        if (clashes(rowId, start, end, item.id)) {
            setRejected(`That would overlap another ${noun} on the same position.`);

            return;
        }

        onChange?.(item, rowId, start, end);
        setFocusId(item.id);
    };

    // Adds an hour in the first free stretch of the row, for people not using a mouse.
    const addToRow = (row: TimelineRow) => {
        const taken = items.filter((item) => item.rowId === row.id).sort((a, b) => a.start - b.start);
        let cursor = 0;

        for (const item of taken) {
            if (item.start - cursor >= SNAP) {
                break;
            }

            cursor = Math.max(cursor, item.end);
        }

        const gapEnd = Math.min(duration, ...taken.filter((item) => item.start >= cursor).map((item) => item.start));

        if (gapEnd - cursor < SNAP) {
            setRejected(`There’s no free time left on ${row.label}.`);

            return;
        }

        onCreate?.(row.id, cursor, Math.min(cursor + 60, gapEnd));
    };

    const external = (event: DragEvent, rowId: number) => {
        if (!onExternalDrop || readOnly) {
            return;
        }

        event.preventDefault();
        setDropTarget({ rowId, minute: minuteAt(event.clientX) });
    };

    // Hour marks, on whole hours where the event starts mid-hour.
    const firstHour = (60 - Number(clock(0).slice(3))) % 60;
    const ticks: number[] = [];
    for (let minute = firstHour; minute <= duration; minute += 60) {
        ticks.push(minute);
    }

    const percent = (minute: number) => `${(minute / duration) * 100}%`;
    const tickLines = ticks
        .filter((minute) => minute > 0 && minute < duration)
        .map((minute) => (
            <span key={minute} aria-hidden="true" className="absolute inset-y-0 border-l border-foreground/5" style={{ left: percent(minute) }} />
        ));

    return (
        <div className="flex flex-col gap-2">
            <div className="overflow-x-auto">
                <div className={cn('min-w-[640px] select-none', drag && 'cursor-grabbing')}>
                    <div className="flex border-b pb-1.5">
                        <div className="w-32 shrink-0" />
                        <div ref={trackRef} className="relative h-5 flex-1">
                            {ticks.map((minute) => (
                                <span
                                    key={minute}
                                    className="absolute -translate-x-1/2 font-mono text-[11px] text-muted-foreground tabular-nums first:translate-x-0 last:-translate-x-full"
                                    style={{ left: percent(minute) }}
                                >
                                    {clock(minute)}
                                    {suffix === 'z' && 'z'}
                                </span>
                            ))}
                        </div>
                    </div>

                    {rows.map((row) => {
                        const rowItems = items.filter((item) => item.rowId === row.id);
                        const ghost = drag && drag.rowId === row.id && drag.end > drag.start ? drag : null;
                        const ghostClashes = ghost && clashes(row.id, ghost.start, ghost.end, ghost.item?.id);

                        return (
                            <div key={row.id} className="flex border-b last:border-b-0">
                                <div
                                    className={cn(
                                        'group/label relative flex w-32 shrink-0 flex-col justify-center py-2',
                                        onCreate && onRemoveRow && !readOnly ? 'pr-14' : 'pr-7',
                                        row.highlighted && 'text-primary',
                                    )}
                                >
                                    <span className="truncate font-mono text-sm font-medium">{row.label}</span>
                                    {row.detail && <span className="truncate font-mono text-xs text-muted-foreground">{row.detail}</span>}
                                    {!readOnly && (onCreate || onRemoveRow) && (
                                        // Shown on hover, and always reachable with the keyboard.
                                        <span className="absolute top-1/2 right-1.5 flex -translate-y-1/2 gap-0.5 opacity-0 group-focus-within/label:opacity-100 group-hover/label:opacity-100">
                                            {onCreate && (
                                                <button
                                                    type="button"
                                                    aria-label={`Add an hour on ${row.label}`}
                                                    className="rounded p-1 text-muted-foreground hover:bg-foreground/10 hover:text-foreground"
                                                    onClick={() => addToRow(row)}
                                                >
                                                    <Plus className="size-3.5" />
                                                </button>
                                            )}
                                            {onRemoveRow && (
                                                <button
                                                    type="button"
                                                    aria-label={`Remove ${row.label}`}
                                                    className="rounded p-1 text-muted-foreground hover:bg-foreground/10 hover:text-foreground"
                                                    onClick={() => onRemoveRow(row.id)}
                                                >
                                                    <Trash2 className="size-3.5" />
                                                </button>
                                            )}
                                        </span>
                                    )}
                                </div>
                                <div
                                    data-timeline-row={row.id}
                                    className={cn(
                                        'relative h-12 flex-1',
                                        row.highlighted && 'bg-primary/5',
                                        !readOnly && onCreate && 'cursor-crosshair',
                                        dropTarget?.rowId === row.id && 'bg-primary/10',
                                    )}
                                    onPointerDown={onCreate ? (event) => begin(event, 'create', row.id) : undefined}
                                    onDragOver={(event) => external(event, row.id)}
                                    onDragLeave={() => setDropTarget(null)}
                                    onDrop={(event) => {
                                        if (onExternalDrop && !readOnly) {
                                            event.preventDefault();
                                            setDropTarget(null);
                                            onExternalDrop(row.id, minuteAt(event.clientX), event.dataTransfer.getData('text/plain'));
                                        }
                                    }}
                                >
                                    {tickLines}

                                    {highlight?.map((span) => (
                                        <span
                                            key={span.start}
                                            aria-hidden="true"
                                            className="absolute inset-y-1 rounded-md bg-success/10 ring-1 ring-success/25 ring-inset"
                                            style={{
                                                left: percent(span.start),
                                                width: percent(span.end - span.start),
                                            }}
                                        />
                                    ))}

                                    {rowItems.length === 0 && !ghost && emptyHint && !readOnly && (
                                        <span className="pointer-events-none absolute inset-0 flex items-center px-3 text-xs text-muted-foreground/70">
                                            {emptyHint}
                                        </span>
                                    )}

                                    {rowItems.map((item) =>
                                        drag?.item?.id === item.id && drag.moved ? null : (
                                            <Block
                                                key={item.id}
                                                item={item}
                                                startsAt={startsAt}
                                                left={percent(item.start)}
                                                width={percent(item.end - item.start)}
                                                readOnly={readOnly}
                                                hintId={hintId}
                                                onPointerDown={(event, kind) => begin(event, kind, row.id, item)}
                                                onKeyDown={(event) => keyboard(event, item)}
                                                onDelete={onDelete}
                                            />
                                        ),
                                    )}

                                    {ghost && (
                                        <span
                                            className={cn(
                                                'pointer-events-none absolute inset-y-1.5 flex items-center overflow-hidden rounded-md px-2 font-mono text-xs ring-2 ring-inset',
                                                ghostClashes
                                                    ? 'bg-destructive/20 text-destructive ring-destructive'
                                                    : 'bg-primary/25 text-foreground ring-primary',
                                            )}
                                            style={{
                                                left: percent(ghost.start),
                                                width: percent(ghost.end - ghost.start),
                                            }}
                                        >
                                            <span className="truncate">
                                                {ghost.item && `${ghost.item.label} · `}
                                                {spanLabel(ghost.start, ghost.end)}
                                            </span>
                                        </span>
                                    )}
                                </div>
                            </div>
                        );
                    })}

                    {footer}
                </div>
            </div>
            {!readOnly && (
                <p id={hintId} className="text-xs text-muted-foreground">
                    With the keyboard: Tab to a {noun}, then use the arrow keys to move it by {SNAP} minutes, Shift + arrows to change its length
                    {allowRowChange && ', up and down to move it to another position'}, and Delete to remove it.
                </p>
            )}
            {rejected && (
                <p role="status" className="text-sm text-destructive">
                    {rejected}
                </p>
            )}
        </div>
    );
}

function Block({
    item,
    startsAt,
    left,
    width,
    readOnly,
    hintId,
    onPointerDown,
    onKeyDown,
    onDelete,
}: {
    item: TimelineItem;
    startsAt: string;
    left: string;
    width: string;
    readOnly: boolean;
    hintId: string;
    onPointerDown: (event: ReactPointerEvent, kind: Drag['kind']) => void;
    onKeyDown: (event: ReactKeyboardEvent) => void;
    onDelete?: (item: TimelineItem) => void;
}): ReactNode {
    const time = useSpanLabel(startsAt).span(item.start, item.end);
    const editable = !readOnly && !item.locked;
    const label = item.locked ? `${item.label}, ${time}. ${item.lockedReason ?? ''}` : `${item.label}, ${time}`;

    return (
        <div
            role="group"
            aria-label={label}
            aria-describedby={editable ? hintId : undefined}
            data-timeline-item={item.id}
            tabIndex={editable ? 0 : undefined}
            title={label}
            className={cn(
                'group absolute inset-y-1.5 flex items-center overflow-hidden rounded-md text-xs ring-1 outline-none ring-inset',
                item.tone === 'muted'
                    ? 'bg-muted text-muted-foreground ring-foreground/15'
                    : item.tone === 'warning'
                      ? 'bg-warning/15 text-warning ring-warning/50'
                      : 'bg-primary/15 text-foreground ring-primary/40',
                editable && 'cursor-grab hover:ring-primary focus-visible:ring-2 focus-visible:ring-ring',
            )}
            style={{ left, width }}
            onPointerDown={(event) => onPointerDown(event, 'move')}
            onKeyDown={onKeyDown}
        >
            {editable && (
                <span
                    aria-hidden="true"
                    className="absolute inset-y-0 left-0 w-2.5 cursor-ew-resize hover:bg-primary/40"
                    onPointerDown={(event) => onPointerDown(event, 'resize-start')}
                />
            )}
            <span className="flex min-w-0 flex-1 flex-col px-2 leading-tight">
                <span className="flex items-center gap-1 truncate font-medium">
                    {item.locked && <Lock className="size-3 shrink-0" />}
                    <span className="truncate">{item.label}</span>
                </span>
                <span className="truncate font-mono text-[11px] text-muted-foreground tabular-nums">{time}</span>
            </span>
            {!readOnly && onDelete && (
                <button
                    type="button"
                    aria-label={`Remove ${item.label}, ${time}`}
                    className="sr-only mr-1 rounded p-0.5 text-muted-foreground group-hover:not-sr-only hover:bg-foreground/10 hover:text-foreground focus-visible:not-sr-only"
                    onPointerDown={(event) => event.stopPropagation()}
                    onClick={() => onDelete(item)}
                >
                    <X className="size-3" />
                </button>
            )}
            {editable && (
                <span
                    aria-hidden="true"
                    className="absolute inset-y-0 right-0 w-2.5 cursor-ew-resize hover:bg-primary/40"
                    onPointerDown={(event) => onPointerDown(event, 'resize-end')}
                />
            )}
        </div>
    );
}
