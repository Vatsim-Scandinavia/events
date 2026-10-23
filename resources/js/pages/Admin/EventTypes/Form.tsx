import { Head, Link, useForm } from '@inertiajs/react';
import { ArrowLeft } from 'lucide-react';
import type { FormEvent, ReactNode } from 'react';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Field } from '@/components/ui/field';
import { Input } from '@/components/ui/input';
import AppLayout from '@/layouts/AppLayout';

// Mirrors the type prop of App\Http\Controllers\Admin\EventTypeController@edit.
type EditableEventType = {
    id: number;
    name: string;
    events: number;
};

export default function EventTypeForm({ type }: { type: EditableEventType | null }) {
    const form = useForm({ name: type?.name ?? '' });

    const submit = (e: FormEvent) => {
        e.preventDefault();

        if (type) {
            form.put(`/admin/event-types/${type.id}`, { preserveScroll: true });
        } else {
            form.post('/admin/event-types', { preserveScroll: true });
        }
    };

    return (
        <>
            <Head title={type ? `${type.name} · Event types` : 'New event type'} />

            <form onSubmit={submit} className="mx-auto flex max-w-3xl flex-col gap-8 px-4 py-8 sm:px-6">
                <div className="flex flex-col gap-3">
                    <Button variant="ghost" size="sm" className="-ml-2.5 self-start" asChild>
                        <Link href="/admin/event-types">
                            <ArrowLeft />
                            All event types
                        </Link>
                    </Button>
                    <h1 className="text-2xl font-semibold tracking-tight">{type ? `Edit ${type.name}` : 'New event type'}</h1>
                    {type && <p className="text-muted-foreground">{type.events === 1 ? '1 event uses it.' : `${type.events} events use it.`}</p>}
                </div>

                <Card>
                    <CardHeader>
                        <CardTitle>Details</CardTitle>
                        <CardDescription>The name staff pick and visitors see on the event, like Fly-in.</CardDescription>
                    </CardHeader>
                    <CardContent>
                        <Field label="Name" error={form.errors.name}>
                            <Input
                                value={form.data.name}
                                onChange={(e) => form.setData('name', e.target.value)}
                                aria-invalid={!!form.errors.name}
                                maxLength={40}
                                placeholder="Fly-in"
                                required
                            />
                        </Field>
                    </CardContent>
                </Card>

                <div className="flex justify-end">
                    <Button type="submit" disabled={form.processing}>
                        {type ? 'Save type' : 'Add type'}
                    </Button>
                </div>
            </form>
        </>
    );
}

EventTypeForm.layout = (page: ReactNode) => <AppLayout>{page}</AppLayout>;
