import { CircleAlert, Undo2 } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { cn } from '@/lib/utils';

/**
 * A bar that sticks to the bottom of the screen while there's something to save, so saving is always in reach on a long page.
 * Used by the staffing planner and the event form.
 */
export function SaveBar({
    dirty,
    saving,
    error,
    onDiscard,
    onSave,
    publish,
    liveNote,
    warning = null,
    status,
    saveLabel = 'Save changes',
    discardLabel = 'Discard',
    discardIcon = true,
    canSave = dirty,
    canDiscard = dirty,
}: {
    dirty: boolean;
    saving: boolean;
    error: string | null;
    onDiscard: () => void;
    onSave: () => void;
    // Planner only: a roster can be saved and published in one go.
    publish?: { published: boolean; onSaveAndPublish: () => void };
    // What saving does, e.g. "Shifts become bookable when you save."
    liveNote?: string;
    warning?: string | null;
    // Replaces "You have unsaved changes" / "All changes saved", e.g. "Not created yet".
    status?: string;
    saveLabel?: string;
    discardLabel?: string;
    discardIcon?: boolean;
    canSave?: boolean;
    canDiscard?: boolean;
}) {
    return (
        <div className="sticky bottom-4 z-30">
            <div
                className={cn(
                    'flex flex-col gap-3 rounded-xl bg-card/95 px-4 py-3 shadow-lg ring-1 shadow-black/40 backdrop-blur sm:flex-row sm:items-center sm:justify-between',
                    error ? 'ring-destructive/50' : dirty ? 'ring-primary/50' : 'ring-foreground/10',
                )}
            >
                <div className="flex flex-col gap-1 text-sm">
                    {error ? (
                        <span role="alert" className="flex items-center gap-2 font-medium text-destructive">
                            <CircleAlert className="size-4 shrink-0" />
                            {error}
                        </span>
                    ) : (
                        <span className="font-medium">{status ?? (dirty ? 'You have unsaved changes' : 'All changes saved')}</span>
                    )}
                    {liveNote && <span className="text-xs text-muted-foreground">{liveNote}</span>}
                    {warning && <span className="text-xs font-medium text-warning">{warning}</span>}
                </div>
                {/* On phones the buttons fill their own row, with the main save last. */}
                <div className="flex flex-wrap gap-2 max-sm:[&>*]:flex-1">
                    <Button type="button" variant="ghost" onClick={onDiscard} disabled={!canDiscard || saving}>
                        {discardIcon && <Undo2 />}
                        {discardLabel}
                    </Button>
                    {publish && !publish.published ? (
                        <>
                            <Button type="button" variant="outline" onClick={onSave} disabled={!canSave || saving}>
                                Save without publishing
                            </Button>
                            <Button type="button" onClick={publish.onSaveAndPublish} disabled={saving}>
                                {dirty ? 'Save and publish' : 'Publish roster'}
                            </Button>
                        </>
                    ) : (
                        <Button type="button" onClick={onSave} disabled={!canSave || saving}>
                            {saveLabel}
                        </Button>
                    )}
                </div>
            </div>
        </div>
    );
}
