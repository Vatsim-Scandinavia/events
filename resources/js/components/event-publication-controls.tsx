import { useForm } from '@inertiajs/react';
import { Globe, LockKeyhole } from 'lucide-react';
import { toast } from 'sonner';
import AlertError from '@/components/alert-error';
import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';
import { destroy, store } from '@/routes/events/publication';
import type { ManagedEvent } from '@/types/events';

export function EventPublicationControls({
    event,
    canPublish,
    canUnpublish,
}: {
    event: Pick<ManagedEvent, 'id' | 'status'>;
    canPublish: boolean;
    canUnpublish: boolean;
}) {
    const form = useForm({});
    const publishing = canPublish && event.status === 'draft';

    return (
        <div className="flex flex-col gap-2">
            <div className="flex flex-wrap gap-2">
                {publishing || canUnpublish ? (
                    <Button
                        type="button"
                        variant={publishing ? 'default' : 'outline'}
                        disabled={form.processing}
                        onClick={() =>
                            form.submit(
                                (publishing ? store : destroy)(event.id),
                                {
                                    preserveScroll: true,
                                    onSuccess: () =>
                                        toast.success(
                                            publishing
                                                ? 'Event published.'
                                                : 'Event unpublished.',
                                        ),
                                },
                            )
                        }
                    >
                        {form.processing ? (
                            <Spinner data-icon="inline-start" />
                        ) : publishing ? (
                            <Globe data-icon="inline-start" />
                        ) : (
                            <LockKeyhole data-icon="inline-start" />
                        )}
                        {publishing ? 'Publish event' : 'Unpublish'}
                    </Button>
                ) : null}
            </div>
            {form.hasErrors ? (
                <AlertError
                    title="Publication could not be changed"
                    errors={Object.values(form.errors)}
                />
            ) : null}
        </div>
    );
}
