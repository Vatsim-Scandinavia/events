import { Head, Link, useForm } from '@inertiajs/react';
import { ArrowLeft, Lock } from 'lucide-react';
import type { FormEvent, ReactNode } from 'react';
import { Field, FieldError } from '@/components/ui/field';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import AppLayout from '@/layouts/AppLayout';
import type { PermissionOption } from '@/types/admin';
import type { PermissionName } from '@/types/inertia';

// Mirrors the role prop of App\Http\Controllers\Admin\RoleController@edit.
type EditableRole = {
    id: number;
    name: string;
    permissions: PermissionName[];
    users: number;
    // Built-in roles keep their name and can't be deleted; Administrator can't be changed at all.
    builtIn: boolean;
    administrator: boolean;
    canChange: boolean;
};

type RoleFormProps = {
    role: EditableRole | null;
    permissions: PermissionOption[];
};

export default function RoleForm({ role, permissions }: RoleFormProps) {
    const form = useForm({ name: role?.name ?? '', permissions: role?.permissions ?? ([] as PermissionName[]) });
    const locked = role !== null && !role.canChange;

    const toggle = (value: PermissionName, on: boolean) =>
        form.setData('permissions', on ? [...form.data.permissions, value] : form.data.permissions.filter((permission) => permission !== value));

    const submit = (e: FormEvent) => {
        e.preventDefault();

        if (role) {
            form.put(`/admin/roles/${role.id}`, { preserveScroll: true });
        } else {
            form.post('/admin/roles', { preserveScroll: true });
        }
    };

    return (
        <>
            <Head title={role ? `${role.name} · Roles` : 'New role'} />

            <form onSubmit={submit} className="mx-auto flex max-w-3xl flex-col gap-8 px-4 py-8 sm:px-6">
                <div className="flex flex-col gap-3">
                    <Button variant="ghost" size="sm" className="-ml-2.5 self-start" asChild>
                        <Link href="/admin/roles">
                            <ArrowLeft />
                            All roles
                        </Link>
                    </Button>
                    <h1 className="text-2xl font-semibold tracking-tight">{role ? (locked ? role.name : `Edit ${role.name}`) : 'New role'}</h1>
                    {role && <p className="text-muted-foreground">{role.users === 1 ? '1 person has' : `${role.users} people have`} this role.</p>}
                </div>

                {locked && (
                    <p className="flex items-start gap-2 rounded-lg bg-muted px-3 py-2.5 text-sm text-muted-foreground">
                        <Lock className="mt-0.5 size-4 shrink-0" />
                        Administrator is built in. It always has every permission and can’t be renamed or deleted.
                    </p>
                )}

                {role?.builtIn && !locked && (
                    <p className="flex items-start gap-2 rounded-lg bg-muted px-3 py-2.5 text-sm text-muted-foreground">
                        <Lock className="mt-0.5 size-4 shrink-0" />
                        {role.name} is built in, so it keeps its name and can’t be deleted. You can still choose what it allows.
                    </p>
                )}

                <Card>
                    <CardHeader>
                        <CardTitle>Name</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <Field label="Role name" error={form.errors.name}>
                            <Input
                                value={form.data.name}
                                onChange={(e) => form.setData('name', e.target.value)}
                                aria-invalid={!!form.errors.name}
                                disabled={locked || role?.builtIn}
                                maxLength={50}
                                required
                            />
                        </Field>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle>Permissions</CardTitle>
                        <CardDescription>What people with this role can do.</CardDescription>
                    </CardHeader>
                    <CardContent className="flex flex-col gap-4">
                        <ul className="flex flex-col divide-y">
                            {permissions.map((permission) => {
                                const id = `permission-${permission.value}`;

                                return (
                                    <li key={permission.value} className="flex items-start gap-3 py-3 first:pt-0 last:pb-0">
                                        <Checkbox
                                            id={id}
                                            className="mt-0.5"
                                            checked={form.data.permissions.includes(permission.value)}
                                            disabled={locked}
                                            onCheckedChange={(checked) => toggle(permission.value, checked === true)}
                                        />
                                        <label htmlFor={id} className="flex flex-1 flex-col gap-0.5">
                                            <span className="font-medium">{permission.label}</span>
                                            <span className="text-sm text-muted-foreground">{permission.description}</span>
                                        </label>
                                    </li>
                                );
                            })}
                        </ul>
                        <FieldError message={form.errors.permissions} />
                    </CardContent>
                </Card>

                {!locked && (
                    <div className="flex justify-end">
                        <Button type="submit" disabled={form.processing}>
                            {role ? 'Save role' : 'Create role'}
                        </Button>
                    </div>
                )}
            </form>
        </>
    );
}

RoleForm.layout = (page: ReactNode) => <AppLayout>{page}</AppLayout>;
