import { Head, Link, router, useForm, usePage } from '@inertiajs/react';
import { ArrowLeft, Plus, Trash2 } from 'lucide-react';
import type { FormEvent, ReactNode } from 'react';
import { RoleBadges } from '@/components/admin-header';
import { useConfirm } from '@/components/confirm-dialog';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { FieldError } from '@/components/ui/field';
import { NativeSelect } from '@/components/ui/native-select';
import AppLayout from '@/layouts/AppLayout';
import type { AdminUser } from '@/types/admin';
import type { Fir } from '@/types/inertia';

// Mirrors the props of App\Http\Controllers\Admin\UserController@edit.
type Assignment = {
    id: number;
    role: string;
    // Null when the role applies across the hub.
    fir: Fir | null;
    // Given by the automatic Controller role, which also takes it away.
    automatic: boolean;
    canRemove: boolean;
};

type AssignableRole = {
    id: number;
    name: string;
    permissions: string[];
    canGrant: boolean;
    // Administrator always applies across the hub.
    hubOnly: boolean;
};

type EditUserProps = {
    user: AdminUser;
    assignments: Assignment[];
    roles: AssignableRole[];
    firs: (Fir & { id: number })[];
};

const describe = (assignment: Assignment) => (assignment.fir ? `${assignment.role} in ${assignment.fir.icao}` : assignment.role);

export default function EditUser({ user, assignments, roles, firs }: EditUserProps) {
    const confirm = useConfirm();
    const { auth, errors } = usePage().props;
    const isMe = auth.user?.id === user.id;
    const form = useForm({ role_id: '' as number | '', fir_id: '' as number | '' });
    const picked = roles.find((role) => role.id === form.data.role_id);

    const add = (e: FormEvent) => {
        e.preventDefault();
        form.post(`/admin/users/${user.id}/roles`, { preserveScroll: true, onSuccess: () => form.reset() });
    };

    const remove = async (assignment: Assignment) => {
        const confirmed = await confirm(
            isMe
                ? {
                      title: `Remove ${describe(assignment)} from yourself?`,
                      description: 'You may lose access to pages you can open now, and someone else would have to give it back.',
                      confirmLabel: 'Remove',
                      destructive: true,
                  }
                : {
                      title: `Remove ${describe(assignment)}?`,
                      description: `${user.name} will no longer be able to do what it allows.`,
                      confirmLabel: 'Remove',
                      destructive: true,
                  },
        );

        if (confirmed) {
            router.delete(`/admin/users/${user.id}/roles/${assignment.id}`, { preserveScroll: true });
        }
    };

    const details = [
        ['VATSIM CID', user.cid ?? '—'],
        ['Rating', user.rating ?? '—'],
        ['Email', user.email],
        ['Joined', user.joinedAt ?? '—'],
    ];

    return (
        <>
            <Head title={`${user.name} · Users`} />

            <div className="mx-auto flex max-w-3xl flex-col gap-8 px-4 py-8 sm:px-6">
                <div className="flex flex-col gap-3">
                    <Button variant="ghost" size="sm" className="-ml-2.5 self-start" asChild>
                        <Link href="/admin/users">
                            <ArrowLeft />
                            All users
                        </Link>
                    </Button>
                    <h1 className="text-2xl font-semibold tracking-tight">{user.name}</h1>
                    <RoleBadges roles={user.roles} />
                </div>

                <Card>
                    <CardHeader>
                        <CardTitle>Details</CardTitle>
                        <CardDescription>These come from VATSIM and can’t be changed here.</CardDescription>
                    </CardHeader>
                    <CardContent>
                        <dl className="grid gap-4 sm:grid-cols-2">
                            {details.map(([label, value]) => (
                                <div key={label} className="flex flex-col gap-0.5">
                                    <dt className="text-xs text-muted-foreground">{label}</dt>
                                    <dd className="truncate tabular-nums">{value}</dd>
                                </div>
                            ))}
                        </dl>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle>Roles</CardTitle>
                        <CardDescription>
                            A role applies across the hub, or only to one FIR’s events. A person can do everything any of their roles allows.
                        </CardDescription>
                    </CardHeader>
                    <CardContent className="flex flex-col gap-5">
                        {assignments.length === 0 ? (
                            <p className="text-sm text-muted-foreground">No roles. They’re a Pilot.</p>
                        ) : (
                            <ul className="flex flex-col divide-y">
                                {assignments.map((assignment) => (
                                    <li key={assignment.id} className="flex items-center gap-3 py-2.5 first:pt-0 last:pb-0">
                                        <span className="flex flex-1 flex-wrap items-center gap-2">
                                            <span className="font-medium">{assignment.role}</span>
                                            {assignment.fir ? (
                                                <span className="flex items-center gap-1.5 text-sm text-muted-foreground">
                                                    in
                                                    <Badge variant="outline" className="font-mono">
                                                        {assignment.fir.icao}
                                                    </Badge>
                                                    {assignment.fir.name !== assignment.fir.icao && assignment.fir.name}
                                                </span>
                                            ) : (
                                                <span className="text-sm text-muted-foreground">across the hub</span>
                                            )}
                                            {assignment.automatic && (
                                                <Badge variant="secondary" title="Given and taken away automatically, by rating and subdivision.">
                                                    Given automatically
                                                </Badge>
                                            )}
                                        </span>
                                        {assignment.canRemove && (
                                            <Button
                                                variant="ghost"
                                                size="sm"
                                                onClick={() => remove(assignment)}
                                                aria-label={`Remove ${describe(assignment)}`}
                                            >
                                                <Trash2 />
                                            </Button>
                                        )}
                                    </li>
                                ))}
                            </ul>
                        )}

                        <form onSubmit={add} className="flex flex-col gap-2 border-t pt-5">
                            <span className="text-sm font-medium">Add a role</span>
                            <div className="flex flex-col gap-2 sm:flex-row">
                                <div className="sm:flex-1">
                                    <NativeSelect
                                        aria-label="Role"
                                        value={form.data.role_id}
                                        onChange={(e) => {
                                            const role = roles.find((item) => item.id === Number(e.target.value));
                                            form.setData((data) => ({ role_id: role?.id ?? '', fir_id: role?.hubOnly ? '' : data.fir_id }));
                                        }}
                                        required
                                    >
                                        <option value="" disabled>
                                            Pick a role
                                        </option>
                                        {roles.map((role) => (
                                            <option key={role.id} value={role.id} disabled={!role.canGrant}>
                                                {role.name}
                                                {role.canGrant ? '' : ' (needs more than you have)'}
                                            </option>
                                        ))}
                                    </NativeSelect>
                                </div>
                                <div className="sm:w-56">
                                    <NativeSelect
                                        aria-label="Where it applies"
                                        value={form.data.fir_id}
                                        onChange={(e) => form.setData('fir_id', e.target.value === '' ? '' : Number(e.target.value))}
                                        disabled={picked?.hubOnly}
                                    >
                                        <option value="">Across the hub</option>
                                        {firs.map((fir) => (
                                            <option key={fir.id} value={fir.id}>
                                                {fir.icao} {fir.name !== fir.icao ? fir.name : ''}
                                            </option>
                                        ))}
                                    </NativeSelect>
                                </div>
                                <Button type="submit" disabled={form.processing || form.data.role_id === ''}>
                                    <Plus />
                                    Add
                                </Button>
                            </div>
                            {picked && picked.permissions.length > 0 && (
                                <span className="text-xs text-muted-foreground">Allows: {picked.permissions.join(', ')}</span>
                            )}
                            <FieldError message={form.errors.role_id ?? form.errors.fir_id ?? errors.roles} />
                        </form>
                    </CardContent>
                </Card>
            </div>
        </>
    );
}

EditUser.layout = (page: ReactNode) => <AppLayout>{page}</AppLayout>;
