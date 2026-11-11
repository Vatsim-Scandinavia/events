import { Head, Link, router } from '@inertiajs/react';
import { ChevronRight, Lock, Plus, Trash2 } from 'lucide-react';
import type { ReactNode } from 'react';
import { AdminHeader } from '@/components/admin-header';
import { useConfirm } from '@/components/confirm-dialog';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/AppLayout';
import { rowLink } from '@/lib/utils';

// Mirrors the roles prop of App\Http\Controllers\Admin\RoleController@index.
type RoleSummary = {
    id: number;
    name: string;
    users: number;
    permissions: string[];
    builtIn: boolean;
    administrator: boolean;
};

const people = (count: number) => `${count} ${count === 1 ? 'person' : 'people'}`;

export default function RolesIndex({ roles }: { roles: RoleSummary[] }) {
    const confirm = useConfirm();

    const destroy = async (role: RoleSummary) => {
        const confirmed = await confirm({
            title: `Delete ${role.name}?`,
            description:
                role.users > 0
                    ? `${people(role.users)} will lose this role and anything it lets them do. This can’t be undone.`
                    : 'Nobody has this role. This can’t be undone.',
            confirmLabel: 'Delete role',
            destructive: true,
        });

        if (confirmed) {
            router.delete(`/admin/roles/${role.id}`, { preserveScroll: true });
        }
    };

    return (
        <>
            <Head title="Roles" />

            <AdminHeader
                title="Roles"
                description="Roles group permissions together. Give someone a role from their page under Users."
                action={
                    <Button className="self-start" asChild>
                        <Link href="/admin/roles/create">
                            <Plus />
                            New role
                        </Link>
                    </Button>
                }
            />

            <div className="mx-auto flex max-w-6xl flex-col gap-4 px-4 py-8 sm:px-6">
                <ul className="divide-y overflow-hidden rounded-xl bg-card ring-1 ring-foreground/10">
                    {roles.map((role) => (
                        <li key={role.id} className="relative flex items-center gap-4 px-4 py-4 transition-colors hover:bg-muted/50">
                            <div className="flex min-w-0 flex-1 flex-col gap-2">
                                <span className="flex flex-wrap items-center gap-2 font-medium">
                                    <Link href={`/admin/roles/${role.id}/edit`} className={rowLink}>
                                        {role.name}
                                    </Link>
                                    {role.builtIn && (
                                        <Badge variant="outline">
                                            <Lock />
                                            Built in
                                        </Badge>
                                    )}
                                    <span className="text-sm font-normal text-muted-foreground">· {people(role.users)}</span>
                                </span>
                                <div className="flex flex-wrap gap-1">
                                    {role.permissions.length > 0 ? (
                                        role.permissions.map((permission) => (
                                            <Badge key={permission} variant="secondary">
                                                {permission}
                                            </Badge>
                                        ))
                                    ) : (
                                        <span className="text-xs text-muted-foreground">No permissions</span>
                                    )}
                                </div>
                            </div>
                            {!role.builtIn && (
                                <Button
                                    variant="ghost"
                                    size="sm"
                                    className="relative z-10"
                                    onClick={() => destroy(role)}
                                    aria-label={`Delete ${role.name}`}
                                >
                                    <Trash2 />
                                </Button>
                            )}
                            <ChevronRight className="size-4 shrink-0 text-muted-foreground" aria-hidden="true" />
                        </li>
                    ))}
                </ul>
            </div>
        </>
    );
}

RolesIndex.layout = (page: ReactNode) => <AppLayout>{page}</AppLayout>;
