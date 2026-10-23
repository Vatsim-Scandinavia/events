import { CircleAlert, Undo2 } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { cn } from '@/lib/utils';

export function SaveBar({
    dirty,
    saving,
    error,
    onDiscard,
    onSave,
    publish,
    liveNote,
    warning,
}: {
    dirty: boolean;
    saving: boolean;
    error: string | null;
    onDiscard: () => void;
    onSave: () => void;
    publish?: { published: boolean; onSaveAndPublish: () => void };
    liveNote: string;
    warning: string | null;
}) {
    return (
        <div className="sticky bottom-4 z-30">
            <div
                className={cn(
                    'flex flex-col gap-3 rounded-xl bg-card/95 px-4 py-3 shadow-lg ring-1 shadow-black/40 backdrop-blur sm:flex-row sm:items-center sm:justify-between',
                    error ? 'ring-destructive/50' : dirty ? 'ring-primary/50' : 'ring-foreground/10',
                )}
            >
                <div className="flex flex-col gap-0.5 text-sm">
                    {error ? (
                        <span role="alert" className="flex items-center gap-2 font-medium text-destructive">
                            <CircleAlert className="size-4 shrink-0" />
                            {error}
                        </span>
                    ) : (
                        <span className="font-medium">{dirty ? 'You have unsaved changes' : 'All changes saved'}</span>
                    )}
                    <span className="text-xs text-muted-foreground">{liveNote}</span>
                    {warning && <span className="text-xs font-medium text-warning">{warning}</span>}
                </div>
                <div className="flex flex-wrap gap-2">
                    <Button variant="ghost" onClick={onDiscard} disabled={!dirty || saving}>
                        <Undo2 />
                        Discard
                    </Button>
                    {publish && !publish.published ? (
                        <>
                            <Button variant="outline" onClick={onSave} disabled={!dirty || saving}>
                                Save without publishing
                            </Button>
                            <Button onClick={publish.onSaveAndPublish} disabled={saving}>
                                {dirty ? 'Save and publish' : 'Publish roster'}
                            </Button>
                        </>
                    ) : (
                        <Button onClick={onSave} disabled={!dirty || saving}>
                            Save changes
                        </Button>
                    )}
                </div>
            </div>
        </div>
    );
}
