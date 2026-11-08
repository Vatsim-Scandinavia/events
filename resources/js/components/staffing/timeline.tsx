import { ChevronDown, ChevronUp, GripVertical, Lock, Plus, Trash2, TriangleAlert, X } from 'lucide-react';
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
import { clashes, SNAP, useTimelineDrag, type Drag } from '@/components/staffing/use-timeline-drag';
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
    // Warnings from Integrations, shown with an icon and read out with the item.
    notes?: string[];
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
    // Moves a row to a new place in the list: shown as a drag handle and up and down buttons on each row's label.
    onMoveRow?: (rowId: number, toIndex: number) => void;
};

// The drag data type for a row being reordered, so it isn't mistaken for something dropped onto a row.
const ROW_DRAG = 'application/x-timeline-row';

const isRowDrag = (event: DragEvent) => event.dataTransfer.types.includes(ROW_DRAG);

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
    onMoveRow,
}: Props) {
    const { clock, span: spanLabel, suffix } = useSpanLabel(startsAt);
    const [dropTarget, setDropTarget] = useState<{
        rowId: number;
        minute: number;
    } | null>(null);
    const [rejected, setRejected] = useState<string | null>(null);
    const { trackRef, drag, begin, minuteAt } = useTimelineDrag({
        noun,
        duration,
        items,
        readOnly,
        allowRowChange,
        onCreate,
        onChange,
        onReject: setRejected,
    });
    const hintId = useId();
    const reorderable = !!onMoveRow && !readOnly;
    // Wider labels make room for the reorder handle without cutting callsigns short.
    const labelWidth = reorderable ? 'w-40' : 'w-32';
    // Where a dragged row would land: the index it would be inserted before.
    const [rowDrop, setRowDrop] = useState<number | null>(null);
    // Moving a row moves its buttons in the page, which can drop focus, so the button pressed
    // takes it back, or the other one when the row has reached the top or bottom.
    const refocusMove = useRef<string | null>(null);
    const refocusButton = (element: HTMLButtonElement | null, rowId: number, direction: 'up' | 'down') => {
        if (element && refocusMove.current === `${rowId}:${direction}`) {
            refocusMove.current = null;
            element.focus();
        }
    };

    const moveRow = (rowId: number, direction: 'up' | 'down') => {
        const to = rows.findIndex((row) => row.id === rowId) + (direction === 'up' ? -1 : 1);
        const focus = to === 0 ? 'down' : to === rows.length - 1 ? 'up' : direction;
        refocusMove.current = `${rowId}:${focus}`;
        onMoveRow?.(rowId, to);
    };

    // A row dragged over the top half of another lands before it, over the bottom half after it.
    const insertionIndex = (event: DragEvent, index: number) => {
        const box = event.currentTarget.getBoundingClientRect();

        return event.clientY > box.top + box.height / 2 ? index + 1 : index;
    };

    const rowDragOver = (event: DragEvent, index: number) => {
        if (!reorderable || !isRowDrag(event)) {
            return;
        }

        event.preventDefault();
        setRowDrop(insertionIndex(event, index));
    };

    const rowDropped = (event: DragEvent, index: number) => {
        if (!reorderable || !isRowDrag(event)) {
            return;
        }

        event.preventDefault();
        setRowDrop(null);
        const rowId = Number(event.dataTransfer.getData(ROW_DRAG));
        const from = rows.findIndex((row) => row.id === rowId);
        const before = insertionIndex(event, index);
        const to = before > from ? before - 1 : before;

        if (from !== -1 && to !== from) {
            onMoveRow?.(rowId, to);
        }
    };

    useEffect(() => {
        if (!rejected) {
            return;
        }

        const timer = setTimeout(() => setRejected(null), 4000);

        return () => clearTimeout(timer);
    }, [rejected]);

    // Keyboard editing: arrows move a span by one snap, Shift+arrows change its length,
    // up and down move it to the next row, and Delete removes it. A span moved to another
    // row is remounted there, so it takes focus back as it renders.
    const refocusId = useRef<number | null>(null);
    const refocus = (element: HTMLDivElement | null, id: number) => {
        if (element && refocusId.current === id) {
            refocusId.current = null;
            element.focus();
        }
    };

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

        if (clashes(items, rowId, start, end, item.id)) {
            setRejected(`That would overlap another ${noun} on the same position.`);

            return;
        }

        onChange?.(item, rowId, start, end);
        refocusId.current = item.id;
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
        if (!onExternalDrop || readOnly || isRowDrag(event)) {
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
                        <div className={cn(labelWidth, 'shrink-0')} />
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

                    {rows.map((row, index) => {
                        const rowItems = items.filter((item) => item.rowId === row.id);
                        const ghost = drag && drag.rowId === row.id && drag.end > drag.start ? drag : null;
                        const ghostClashes = ghost && clashes(items, row.id, ghost.start, ghost.end, ghost.item?.id);

                        return (
                            <div
                                key={row.id}
                                className="relative flex border-b last:border-b-0"
                                onDragOver={(event) => rowDragOver(event, index)}
                                onDrop={(event) => rowDropped(event, index)}
                            >
                                {rowDrop !== null && (rowDrop === index || (rowDrop === rows.length && index === rows.length - 1)) && (
                                    <span
                                        aria-hidden="true"
                                        className={cn(
                                            'pointer-events-none absolute inset-x-0 z-10 h-0.5 bg-primary',
                                            rowDrop === index ? '-top-px' : '-bottom-px',
                                        )}
                                    />
                                )}
                                <div
                                    className={cn(
                                        'group/label relative flex shrink-0 flex-col justify-center py-2',
                                        labelWidth,
                                        onCreate && onRemoveRow && !readOnly ? 'pr-14' : 'pr-7',
                                        reorderable && 'pl-5',
                                        row.highlighted && 'text-primary',
                                    )}
                                >
                                    {reorderable && (
                                        <span
                                            draggable
                                            title={`Drag to move ${row.label}`}
                                            className="absolute inset-y-0 left-0 flex w-4 cursor-grab flex-col items-center justify-center text-muted-foreground"
                                            onDragStart={(event) => {
                                                event.dataTransfer.setData(ROW_DRAG, String(row.id));
                                                event.dataTransfer.effectAllowed = 'move';
                                            }}
                                            onDragEnd={() => setRowDrop(null)}
                                        >
                                            <MoveRowButton
                                                row={row}
                                                direction="up"
                                                disabled={index === 0}
                                                onMove={moveRow}
                                                focusRef={refocusButton}
                                            />
                                            <GripVertical aria-hidden="true" className="size-3.5 shrink-0 opacity-60" />
                                            <MoveRowButton
                                                row={row}
                                                direction="down"
                                                disabled={index === rows.length - 1}
                                                onMove={moveRow}
                                                focusRef={refocusButton}
                                            />
                                        </span>
                                    )}
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
                                        if (onExternalDrop && !readOnly && !isRowDrag(event)) {
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
                                                focusRef={(element) => refocus(element, item.id)}
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

function MoveRowButton({
    row,
    direction,
    disabled,
    onMove,
    focusRef,
}: {
    row: TimelineRow;
    direction: 'up' | 'down';
    disabled: boolean;
    onMove: (rowId: number, direction: 'up' | 'down') => void;
    focusRef: (element: HTMLButtonElement | null, rowId: number, direction: 'up' | 'down') => void;
}) {
    const Icon = direction === 'up' ? ChevronUp : ChevronDown;

    return (
        // Shown on hover, and always reachable with the keyboard.
        <button
            type="button"
            aria-label={`Move ${row.label} ${direction}`}
            ref={(element) => focusRef(element, row.id, direction)}
            disabled={disabled}
            className="rounded opacity-0 group-focus-within/label:opacity-100 group-hover/label:opacity-100 hover:bg-foreground/10 hover:text-foreground disabled:invisible"
            onClick={() => onMove(row.id, direction)}
        >
            <Icon className="size-3.5" />
        </button>
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
    focusRef,
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
    focusRef: (element: HTMLDivElement | null) => void;
}): ReactNode {
    const time = useSpanLabel(startsAt).span(item.start, item.end);
    const editable = !readOnly && !item.locked;
    const notes = item.notes?.length ? ` ${item.notes.join(' ')}` : '';
    const label = (item.locked ? `${item.label}, ${time}. ${item.lockedReason ?? ''}` : `${item.label}, ${time}`) + notes;

    return (
        <div
            ref={focusRef}
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
                    {item.notes?.length ? <TriangleAlert className="size-3 shrink-0 text-warning" aria-hidden /> : null}
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
