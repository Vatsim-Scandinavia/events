import { router, useHttp, usePage } from '@inertiajs/react';
import { Globe2, Info, LockKeyhole, Plus, Trash2 } from 'lucide-react';
import { useState } from 'react';
import type { FormEvent } from 'react';
import { toast } from 'sonner';
import {
    destroy,
    store,
} from '@/actions/App/Http/Controllers/UserRoleController';
import InputError from '@/components/input-error';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectGroup,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Separator } from '@/components/ui/separator';
import { Spinner } from '@/components/ui/spinner';
import { dashboard } from '@/routes';
import type { ManagedUser, RoleGrant, UserRole, UserTeam } from '@/types/users';

type Props = {
    user: ManagedUser;
    teams: UserTeam[];
    roles: UserRole[];
    onClose: () => void;
    returnFocus: () => void;
};

export function UserRoleDialog({
    user,
    teams,
    roles,
    onClose,
    returnFocus,
}: Props) {
    const { auth } = usePage().props;
    const http = useHttp({ role: '', team_id: '' });
    const [busy, setBusy] = useState(false);
    const [removing, setRemoving] = useState<RoleGrant | null>(null);
    const [failure, setFailure] = useState<string | null>(null);
    const selectedRole = roles.find((role) => role.name === http.data.role);
    const selectedTeamId = selectedRole?.is_global
        ? null
        : Number(http.data.team_id);
    const alreadyAssigned = user.grants.some(
        (grant) =>
            grant.source === 'manual' &&
            grant.role === http.data.role &&
            grant.team_id === selectedTeamId,
    );

    async function save(grant?: RoleGrant) {
        setBusy(true);
        setFailure(null);
        http.clearErrors();
        http.transform(() => ({
            role: grant ? grant.role : http.data.role,
            team_id: grant
                ? grant.team_id
                : selectedRole?.is_global
                  ? null
                  : http.data.team_id,
        }));

        try {
            await http.submit(grant ? destroy(user.cid) : store(user.cid));
            toast.success(grant ? 'Manual role removed.' : 'Role assigned.');
            setRemoving(null);
            http.resetAndClearErrors();

            const losesOwnAccess =
                grant?.role === 'Administrator' &&
                user.cid === auth.user.cid &&
                !user.grants.some(
                    (other) =>
                        other.id !== grant.id && other.role === 'Administrator',
                );

            if (losesOwnAccess) {
                router.visit(dashboard());
                return;
            }

            router.reload({
                only: ['users', 'auth'],
                onFinish: () => setBusy(false),
            });
        } catch {
            setFailure(
                'The change could not be saved. Check the fields below or try again.',
            );
            setBusy(false);
        }
    }

    function submit(event: FormEvent<HTMLFormElement>) {
        event.preventDefault();
        void save();
    }

    return (
        <Dialog
            open
            onOpenChange={(open) => {
                if (!open && !busy) onClose();
            }}
        >
            <DialogContent
                className="max-h-[90dvh] overflow-y-auto sm:max-w-2xl"
                onCloseAutoFocus={(event) => {
                    event.preventDefault();
                    returnFocus();
                }}
            >
                <DialogHeader>
                    <DialogTitle>Manage roles</DialogTitle>
                    <DialogDescription>
                        {user.name_full} · CID {user.cid}
                    </DialogDescription>
                </DialogHeader>

                <dl className="grid grid-cols-2 gap-4 text-sm">
                    <div className="col-span-2 flex flex-col gap-1 sm:col-span-1">
                        <dt className="text-muted-foreground">Email</dt>
                        <dd className="break-all">{user.email}</dd>
                    </div>
                    <div className="flex flex-col gap-1">
                        <dt className="text-muted-foreground">Organisation</dt>
                        <dd>
                            {[user.division, user.subdivision]
                                .filter(Boolean)
                                .join(' / ') || 'Not provided'}
                        </dd>
                    </div>
                </dl>
                <p className="text-muted-foreground text-xs">
                    Profile details are supplied by the sign-in provider.
                </p>
                <Separator />

                <section
                    className="flex flex-col gap-3"
                    aria-labelledby="assigned-roles-title"
                >
                    <h3
                        id="assigned-roles-title"
                        className="text-sm font-semibold"
                    >
                        Current assignments
                    </h3>
                    {user.grants.length === 0 ? (
                        <Alert>
                            <Globe2 />
                            <AlertDescription>
                                This user has the default Pilot role. Add an
                                assignment to give them additional access.
                            </AlertDescription>
                        </Alert>
                    ) : (
                        <ul className="flex flex-col gap-2">
                            {user.grants.map((grant) => {
                                const team = teams.find(
                                    (team) => team.id === grant.team_id,
                                );
                                return (
                                    <li
                                        key={grant.id}
                                        className="flex flex-wrap items-center justify-between gap-3 rounded-lg border p-3"
                                    >
                                        <div className="flex min-w-0 flex-col gap-1">
                                            <p className="text-sm font-medium">
                                                {grant.role ?? 'Unknown role'}
                                            </p>
                                            <p className="text-muted-foreground text-xs">
                                                {grant.team_id === null
                                                    ? 'Global access'
                                                    : `${team?.code ?? 'FIR'} · ${team?.name ?? 'Unavailable'}`}
                                            </p>
                                        </div>
                                        <div className="flex items-center gap-2">
                                            <Badge variant="outline">
                                                {grant.source !== 'manual' && (
                                                    <LockKeyhole data-icon="inline-start" />
                                                )}
                                                {grant.source === 'manual'
                                                    ? 'Manual'
                                                    : `Synced: ${grant.source}`}
                                            </Badge>
                                            {grant.source === 'manual' && (
                                                <Button
                                                    variant="ghost"
                                                    size="icon"
                                                    disabled={busy}
                                                    aria-label={`Remove ${grant.role} for ${team?.code ?? 'global access'}`}
                                                    onClick={() => {
                                                        setRemoving(grant);
                                                        setFailure(null);
                                                        http.clearErrors();
                                                    }}
                                                >
                                                    <Trash2 data-icon="inline-start" />
                                                </Button>
                                            )}
                                        </div>
                                    </li>
                                );
                            })}
                        </ul>
                    )}
                    {user.grants.some((grant) => grant.source !== 'manual') && (
                        <p className="text-muted-foreground text-xs">
                            Synced assignments are managed by their source.
                            Removing a manual assignment keeps any synced access
                            in place.
                        </p>
                    )}
                </section>

                {failure && (
                    <Alert variant="destructive">
                        <AlertDescription>{failure}</AlertDescription>
                    </Alert>
                )}

                {removing ? (
                    <Alert>
                        <Info />
                        <AlertDescription className="flex flex-col gap-3">
                            <p>
                                Remove the manual {removing.role} assignment
                                {removing.team_id === null
                                    ? ''
                                    : ` for ${teams.find((team) => team.id === removing.team_id)?.code}`}
                                ?
                            </p>
                            {removing.role === 'Administrator' &&
                                user.cid === auth.user.cid && (
                                    <p>
                                        Removing your administrator access may
                                        return you to the dashboard.
                                    </p>
                                )}
                            {http.hasErrors && (
                                <p>{Object.values(http.errors).join(' ')}</p>
                            )}
                            <div className="flex gap-2">
                                <Button
                                    variant="destructive"
                                    size="sm"
                                    disabled={busy}
                                    onClick={() => void save(removing)}
                                >
                                    {busy && <Spinner />}Remove role
                                </Button>
                                <Button
                                    variant="outline"
                                    size="sm"
                                    disabled={busy}
                                    onClick={() => setRemoving(null)}
                                >
                                    Cancel
                                </Button>
                            </div>
                        </AlertDescription>
                    </Alert>
                ) : (
                    <>
                        <Separator />
                        <form onSubmit={submit} className="flex flex-col gap-4">
                            <h3 className="text-sm font-semibold">
                                Add a role
                            </h3>
                            <div className="grid gap-4 sm:grid-cols-2">
                                <div className="flex flex-col gap-2">
                                    <Label htmlFor="assignment-role">
                                        Role
                                    </Label>
                                    <Select
                                        value={http.data.role}
                                        disabled={busy}
                                        onValueChange={(role) => {
                                            http.setData({ role, team_id: '' });
                                            http.clearErrors();
                                        }}
                                    >
                                        <SelectTrigger
                                            id="assignment-role"
                                            className="w-full"
                                            aria-invalid={!!http.errors.role}
                                            aria-describedby="assignment-role-error"
                                        >
                                            <SelectValue placeholder="Choose a role" />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectGroup>
                                                {roles
                                                    .filter(
                                                        (role) =>
                                                            role.assignable,
                                                    )
                                                    .map((role) => (
                                                        <SelectItem
                                                            key={role.name}
                                                            value={role.name}
                                                        >
                                                            {role.name}
                                                        </SelectItem>
                                                    ))}
                                            </SelectGroup>
                                        </SelectContent>
                                    </Select>
                                    <InputError
                                        id="assignment-role-error"
                                        message={http.errors.role}
                                    />
                                </div>
                                <div className="flex flex-col gap-2">
                                    <Label htmlFor="assignment-fir">FIR</Label>
                                    <Select
                                        value={http.data.team_id}
                                        disabled={
                                            busy ||
                                            !selectedRole ||
                                            selectedRole.is_global
                                        }
                                        onValueChange={(team_id) =>
                                            http.setData('team_id', team_id)
                                        }
                                    >
                                        <SelectTrigger
                                            id="assignment-fir"
                                            className="w-full"
                                            aria-invalid={!!http.errors.team_id}
                                            aria-describedby="assignment-fir-error"
                                        >
                                            <SelectValue
                                                placeholder={
                                                    selectedRole?.is_global
                                                        ? 'Global access'
                                                        : 'Choose a FIR'
                                                }
                                            />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectGroup>
                                                {teams.map((team) => (
                                                    <SelectItem
                                                        key={team.id}
                                                        value={String(team.id)}
                                                    >
                                                        {team.code} ·{' '}
                                                        {team.name}
                                                    </SelectItem>
                                                ))}
                                            </SelectGroup>
                                        </SelectContent>
                                    </Select>
                                    <InputError
                                        id="assignment-fir-error"
                                        message={http.errors.team_id}
                                    />
                                </div>
                            </div>
                            <p className="text-muted-foreground text-xs">
                                {selectedRole?.is_global
                                    ? 'Administrators can manage users and all FIRs.'
                                    : teams.length === 0
                                      ? 'No FIRs are configured. Only global roles can be assigned.'
                                      : 'FIR roles apply only within the selected flight information region.'}
                            </p>
                            <div className="flex items-center justify-between gap-3">
                                <p
                                    className="text-muted-foreground text-xs"
                                    role="status"
                                >
                                    {alreadyAssigned
                                        ? 'This manual assignment already exists.'
                                        : 'Pilot is automatic when no roles are assigned.'}
                                </p>
                                <Button
                                    type="submit"
                                    disabled={
                                        busy ||
                                        !selectedRole ||
                                        (!selectedRole.is_global &&
                                            !http.data.team_id) ||
                                        alreadyAssigned
                                    }
                                >
                                    {busy ? (
                                        <Spinner />
                                    ) : (
                                        <Plus data-icon="inline-start" />
                                    )}
                                    Add role
                                </Button>
                            </div>
                        </form>
                    </>
                )}
                <DialogFooter>
                    <Button variant="outline" disabled={busy} onClick={onClose}>
                        Done
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}
