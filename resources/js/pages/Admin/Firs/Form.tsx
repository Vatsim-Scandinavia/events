import { Head, Link, useForm } from '@inertiajs/react';
import { ArrowLeft } from 'lucide-react';
import type { FormEvent, ReactNode } from 'react';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Field } from '@/components/ui/field';
import { Input } from '@/components/ui/input';
import AppLayout from '@/layouts/AppLayout';

// Mirrors the fir prop of App\Http\Controllers\Admin\FirController@edit.
type EditableFir = {
    id: number;
    icao: string;
    name: string;
    events: number;
};

export default function FirForm({ fir }: { fir: EditableFir | null }) {
    const form = useForm({ icao: fir?.icao ?? '', name: fir?.name ?? '' });

    const submit = (e: FormEvent) => {
        e.preventDefault();

        if (fir) {
            form.put(`/admin/firs/${fir.id}`, { preserveScroll: true });
        } else {
            form.post('/admin/firs', { preserveScroll: true });
        }
    };

    return (
        <>
            <Head title={fir ? `${fir.icao} · FIRs` : 'New FIR'} />

            <form onSubmit={submit} className="mx-auto flex max-w-3xl flex-col gap-8 px-4 py-8 sm:px-6">
                <div className="flex flex-col gap-3">
                    <Button variant="ghost" size="sm" className="-ml-2.5 self-start" asChild>
                        <Link href="/admin/firs">
                            <ArrowLeft />
                            All FIRs
                        </Link>
                    </Button>
                    <h1 className="text-2xl font-semibold tracking-tight">{fir ? `Edit ${fir.icao} ${fir.name}` : 'New FIR'}</h1>
                    {fir && <p className="text-muted-foreground">{fir.events === 1 ? 'It runs 1 event.' : `It runs ${fir.events} events.`}</p>}
                </div>

                <Card>
                    <CardHeader>
                        <CardTitle>Details</CardTitle>
                        <CardDescription>The FIR’s ICAO code and the name people know it by, like EKDK Copenhagen.</CardDescription>
                    </CardHeader>
                    <CardContent className="grid gap-4 sm:grid-cols-[10rem_1fr]">
                        <Field label="ICAO code" error={form.errors.icao}>
                            <Input
                                value={form.data.icao}
                                onChange={(e) => form.setData('icao', e.target.value.toUpperCase())}
                                aria-invalid={!!form.errors.icao}
                                className="font-mono"
                                maxLength={4}
                                placeholder="EKDK"
                                required
                            />
                        </Field>
                        <Field label="Name" error={form.errors.name}>
                            <Input
                                value={form.data.name}
                                onChange={(e) => form.setData('name', e.target.value)}
                                aria-invalid={!!form.errors.name}
                                maxLength={60}
                                placeholder="Copenhagen"
                                required
                            />
                        </Field>
                    </CardContent>
                </Card>

                <div className="flex justify-end">
                    <Button type="submit" disabled={form.processing}>
                        {fir ? 'Save FIR' : 'Add FIR'}
                    </Button>
                </div>
            </form>
        </>
    );
}

FirForm.layout = (page: ReactNode) => <AppLayout>{page}</AppLayout>;
