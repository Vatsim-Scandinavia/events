import { Head, Link, useForm } from '@inertiajs/react';
import {
    ChevronLeft,
    ChevronRight,
    Globe2,
    Pencil,
    Plus,
    Search,
    Trash2,
} from 'lucide-react';
import { useRef, useState } from 'react';
import { DeleteFirDialog, FirDialog } from '@/components/fir-dialog';
import InputError from '@/components/input-error';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { index } from '@/routes/firs';
import { index as usersIndex } from '@/routes/users';
import type { ManagedFir, PaginatedFirs } from '@/types/firs';

type Props = {
    firs: PaginatedFirs;
    filters: { search: string };
};

type FirAction =
    | { type: 'create' }
    | { type: 'edit' | 'delete'; fir: ManagedFir };

export default function Firs({ firs, filters }: Props) {
    const form = useForm(filters);
    const [action, setAction] = useState<FirAction | null>(null);
    const trigger = useRef<HTMLButtonElement | null>(null);
    const createButton = useRef<HTMLButtonElement | null>(null);
    const pageUrl = (page: number) => index({ query: { ...filters, page } });
    const close = () => setAction(null);
    const returnFocus = () => {
        (trigger.current?.isConnected
            ? trigger.current
            : createButton.current
        )?.focus();
    };

    return (
        <>
            <Head title="FIRs" />
            <div className="flex flex-1 flex-col gap-6 p-4 md:p-8">
                <header className="flex flex-wrap items-start justify-between gap-4">
                    <div className="flex flex-col gap-2">
                        <div className="flex items-center gap-3">
                            <h1 className="text-2xl font-semibold tracking-tight">
                                FIRs
                            </h1>
                            <Badge variant="secondary">
                                {firs.total}{' '}
                                {filters.search ? 'matching' : 'total'}
                            </Badge>
                        </div>
                        <p className="text-muted-foreground text-sm">
                            Manage flight information regions and find their
                            members.
                        </p>
                    </div>
                    <Button
                        ref={createButton}
                        onClick={(event) => {
                            trigger.current = event.currentTarget;
                            setAction({ type: 'create' });
                        }}
                    >
                        <Plus data-icon="inline-start" />
                        Create FIR
                    </Button>
                </header>

                <div className="flex min-w-0 flex-col overflow-hidden rounded-xl border">
                    <form
                        className="flex flex-col gap-3 p-4"
                        onSubmit={(event) => {
                            event.preventDefault();
                            form.get(index.url(), {
                                preserveState: false,
                                preserveScroll: true,
                            });
                        }}
                    >
                        <div className="flex flex-col gap-3 sm:flex-row sm:items-end">
                            <div className="flex flex-1 flex-col gap-2">
                                <Label htmlFor="fir-search">Search FIRs</Label>
                                <Input
                                    id="fir-search"
                                    name="search"
                                    type="search"
                                    maxLength={255}
                                    value={form.data.search}
                                    onChange={(event) =>
                                        form.setData(
                                            'search',
                                            event.target.value,
                                        )
                                    }
                                    placeholder="Code or name"
                                    aria-invalid={!!form.errors.search}
                                    aria-describedby="fir-search-error"
                                />
                            </div>
                            <Button type="submit" disabled={form.processing}>
                                {form.processing ? (
                                    <Spinner />
                                ) : (
                                    <Search data-icon="inline-start" />
                                )}
                                Search
                            </Button>
                            {filters.search ? (
                                <Button variant="ghost" asChild>
                                    <Link href={index()}>Clear search</Link>
                                </Button>
                            ) : null}
                        </div>
                        <InputError
                            id="fir-search-error"
                            message={form.errors.search}
                        />
                    </form>

                    <div className="overflow-x-auto">
                        <table className="w-full text-left text-sm">
                            <caption className="sr-only">
                                Flight information regions and the number of
                                members with assigned roles.
                            </caption>
                            <thead className="bg-muted/50 text-muted-foreground border-y">
                                <tr>
                                    <th
                                        scope="col"
                                        className="px-4 py-3 font-medium"
                                    >
                                        FIR
                                    </th>
                                    <th
                                        scope="col"
                                        className="hidden px-4 py-3 font-medium sm:table-cell"
                                    >
                                        Members
                                    </th>
                                    <th scope="col" className="px-4 py-3">
                                        <span className="sr-only">Actions</span>
                                    </th>
                                </tr>
                            </thead>
                            <tbody className="divide-y">
                                {firs.data.map((fir) => (
                                    <tr
                                        key={fir.id}
                                        className="hover:bg-muted/30 transition-colors"
                                    >
                                        <td className="px-4 py-4">
                                            <div className="flex flex-col items-start gap-1.5">
                                                <Badge variant="outline">
                                                    {fir.code}
                                                </Badge>
                                                <span className="font-medium wrap-anywhere">
                                                    {fir.name}
                                                </span>
                                                <Link
                                                    href={usersIndex({
                                                        query: {
                                                            team_id: fir.id,
                                                        },
                                                    })}
                                                    className="text-muted-foreground text-xs underline underline-offset-4 sm:hidden"
                                                >
                                                    {fir.members_count}{' '}
                                                    {fir.members_count === 1
                                                        ? 'member'
                                                        : 'members'}
                                                </Link>
                                            </div>
                                        </td>
                                        <td className="hidden px-4 py-4 sm:table-cell">
                                            <Link
                                                href={usersIndex({
                                                    query: { team_id: fir.id },
                                                })}
                                                className="underline underline-offset-4"
                                                aria-label={`View members of ${fir.code}`}
                                            >
                                                {fir.members_count}{' '}
                                                {fir.members_count === 1
                                                    ? 'member'
                                                    : 'members'}
                                            </Link>
                                        </td>
                                        <td className="px-4 py-4">
                                            <div className="flex items-center justify-end gap-2">
                                                <Button
                                                    variant="outline"
                                                    size="sm"
                                                    aria-label={`Edit ${fir.code}`}
                                                    onClick={(event) => {
                                                        trigger.current =
                                                            event.currentTarget;
                                                        setAction({
                                                            type: 'edit',
                                                            fir,
                                                        });
                                                    }}
                                                >
                                                    <Pencil data-icon="inline-start" />
                                                    <span className="hidden sm:inline">
                                                        Edit
                                                    </span>
                                                </Button>
                                                <Button
                                                    variant="ghost"
                                                    size="icon"
                                                    aria-label={`Delete ${fir.code}`}
                                                    onClick={(event) => {
                                                        trigger.current =
                                                            event.currentTarget;
                                                        setAction({
                                                            type: 'delete',
                                                            fir,
                                                        });
                                                    }}
                                                >
                                                    <Trash2 />
                                                </Button>
                                            </div>
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>

                    {firs.data.length === 0 ? (
                        <div className="flex flex-col items-center gap-3 px-6 py-14 text-center">
                            <Globe2 className="text-muted-foreground size-8" />
                            <h2 className="font-semibold">
                                {filters.search
                                    ? 'No FIRs found'
                                    : 'No FIRs yet'}
                            </h2>
                            <p className="text-muted-foreground max-w-sm text-sm">
                                {filters.search
                                    ? 'Try a different code or name, or clear your search.'
                                    : 'Create your first flight information region, then assign member roles from the Users page.'}
                            </p>
                            {filters.search ? (
                                <Button variant="outline" asChild>
                                    <Link href={index()}>Clear search</Link>
                                </Button>
                            ) : (
                                <Button
                                    variant="outline"
                                    onClick={(event) => {
                                        trigger.current = event.currentTarget;
                                        setAction({ type: 'create' });
                                    }}
                                >
                                    Create FIR
                                </Button>
                            )}
                        </div>
                    ) : null}

                    <footer className="text-muted-foreground flex flex-wrap items-center justify-between gap-3 border-t px-4 py-3 text-sm">
                        <span>
                            {firs.total === 0
                                ? '0 FIRs'
                                : `Showing ${firs.from ?? 0}–${firs.to ?? 0} of ${firs.total} FIRs`}
                        </span>
                        <nav
                            aria-label="FIR pagination"
                            className="flex items-center gap-2"
                        >
                            {firs.current_page > 1 ? (
                                <Button variant="outline" size="sm" asChild>
                                    <Link href={pageUrl(firs.current_page - 1)}>
                                        <ChevronLeft data-icon="inline-start" />
                                        Previous
                                    </Link>
                                </Button>
                            ) : (
                                <Button variant="outline" size="sm" disabled>
                                    <ChevronLeft data-icon="inline-start" />
                                    Previous
                                </Button>
                            )}
                            {firs.current_page < firs.last_page ? (
                                <Button variant="outline" size="sm" asChild>
                                    <Link href={pageUrl(firs.current_page + 1)}>
                                        Next
                                        <ChevronRight data-icon="inline-end" />
                                    </Link>
                                </Button>
                            ) : (
                                <Button variant="outline" size="sm" disabled>
                                    Next
                                    <ChevronRight data-icon="inline-end" />
                                </Button>
                            )}
                        </nav>
                    </footer>
                </div>
            </div>
            {action?.type === 'create' || action?.type === 'edit' ? (
                <FirDialog
                    key={action.type === 'edit' ? action.fir.id : 'create'}
                    fir={action.type === 'edit' ? action.fir : undefined}
                    onClose={close}
                    returnFocus={returnFocus}
                />
            ) : null}
            {action?.type === 'delete' ? (
                <DeleteFirDialog
                    fir={action.fir}
                    onClose={close}
                    returnFocus={returnFocus}
                />
            ) : null}
        </>
    );
}
