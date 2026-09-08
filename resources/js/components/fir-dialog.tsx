import { Link, useForm } from '@inertiajs/react';
import { toast } from 'sonner';
import {
    destroy,
    store,
    update,
} from '@/actions/App/Http/Controllers/FirController';
import InputError from '@/components/input-error';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { index as usersIndex } from '@/routes/users';
import type { ManagedFir } from '@/types/firs';

type DialogProps = {
    onClose: () => void;
    returnFocus: () => void;
};

export function FirDialog({
    fir,
    onClose,
    returnFocus,
}: DialogProps & { fir?: ManagedFir }) {
    const form = useForm({ code: fir?.code ?? '', name: fir?.name ?? '' });

    return (
        <Dialog
            open
            onOpenChange={(open) => {
                if (!open && !form.processing) onClose();
            }}
        >
            <DialogContent
                className="max-h-[90dvh] overflow-y-auto"
                onCloseAutoFocus={(event) => {
                    event.preventDefault();
                    returnFocus();
                }}
            >
                <DialogHeader>
                    <DialogTitle>{fir ? 'Edit FIR' : 'Create FIR'}</DialogTitle>
                    <DialogDescription>
                        {fir
                            ? 'Update the code or name. Existing member roles stay with this FIR.'
                            : 'Add a flight information region to organize members and their roles.'}
                    </DialogDescription>
                </DialogHeader>
                <form
                    className="flex flex-col gap-6"
                    onSubmit={(event) => {
                        event.preventDefault();
                        form.submit(fir ? update(fir.id) : store(), {
                            preserveScroll: true,
                            onSuccess: () => {
                                toast.success(
                                    fir ? 'FIR updated.' : 'FIR created.',
                                );
                                onClose();
                            },
                        });
                    }}
                >
                    <div className="flex flex-col gap-2">
                        <Label htmlFor="fir-code">FIR code</Label>
                        <Input
                            id="fir-code"
                            name="code"
                            value={form.data.code}
                            onChange={(event) =>
                                form.setData(
                                    'code',
                                    event.target.value.toUpperCase(),
                                )
                            }
                            placeholder="EKDK"
                            minLength={4}
                            maxLength={4}
                            pattern="[A-Za-z]{4}"
                            title="Enter exactly 4 letters (A-Z)."
                            autoComplete="off"
                            required
                            disabled={form.processing}
                            aria-invalid={!!form.errors.code}
                            aria-describedby="fir-code-hint fir-code-error"
                        />
                        <p
                            id="fir-code-hint"
                            className="text-muted-foreground text-xs"
                        >
                            Exactly 4 letters (A-Z), e.g. EKDK. Codes must be
                            unique.
                        </p>
                        <InputError
                            id="fir-code-error"
                            message={form.errors.code}
                        />
                    </div>
                    <div className="flex flex-col gap-2">
                        <Label htmlFor="fir-name">FIR name</Label>
                        <Input
                            id="fir-name"
                            name="name"
                            value={form.data.name}
                            onChange={(event) =>
                                form.setData('name', event.target.value)
                            }
                            placeholder="Copenhagen FIR"
                            maxLength={255}
                            required
                            disabled={form.processing}
                            aria-invalid={!!form.errors.name}
                            aria-describedby="fir-name-error"
                        />
                        <InputError
                            id="fir-name-error"
                            message={form.errors.name}
                        />
                    </div>
                    <DialogFooter>
                        <Button
                            type="button"
                            variant="outline"
                            onClick={onClose}
                            disabled={form.processing}
                        >
                            Cancel
                        </Button>
                        <Button type="submit" disabled={form.processing}>
                            {form.processing ? <Spinner /> : null}
                            {fir ? 'Save changes' : 'Create FIR'}
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}

export function DeleteFirDialog({
    fir,
    onClose,
    returnFocus,
}: DialogProps & { fir: ManagedFir }) {
    const form = useForm<{ fir?: string }>({});
    const hasMembers = fir.members_count > 0;

    return (
        <Dialog
            open
            onOpenChange={(open) => {
                if (!open && !form.processing) onClose();
            }}
        >
            <DialogContent
                onCloseAutoFocus={(event) => {
                    event.preventDefault();
                    returnFocus();
                }}
            >
                <DialogHeader>
                    <DialogTitle>Delete {fir.code}?</DialogTitle>
                    <DialogDescription>
                        {hasMembers
                            ? `${fir.name} still has members with assigned roles.`
                            : `${fir.name} will be permanently deleted. This cannot be undone.`}
                    </DialogDescription>
                </DialogHeader>
                {hasMembers ? (
                    <Alert>
                        <AlertDescription>
                            Remove all role assignments before deleting this
                            FIR. Assignments from external sources must be
                            removed at their source.
                        </AlertDescription>
                    </Alert>
                ) : null}
                <InputError message={form.errors.fir} />
                <DialogFooter>
                    <Button
                        type="button"
                        variant="outline"
                        onClick={onClose}
                        disabled={form.processing}
                        autoFocus
                    >
                        Cancel
                    </Button>
                    {hasMembers ? (
                        <Button asChild>
                            <Link
                                href={usersIndex({
                                    query: { team_id: fir.id },
                                })}
                            >
                                View members
                            </Link>
                        </Button>
                    ) : (
                        <Button
                            type="button"
                            variant="destructive"
                            disabled={form.processing}
                            onClick={() =>
                                form.submit(destroy(fir.id), {
                                    preserveScroll: true,
                                    onSuccess: () => {
                                        toast.success('FIR deleted.');
                                        onClose();
                                    },
                                })
                            }
                        >
                            {form.processing ? <Spinner /> : null}
                            Delete FIR
                        </Button>
                    )}
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}
