import { Head, Link, useForm } from '@inertiajs/react';
import { Lock } from 'lucide-react';
import type { FormEvent, ReactNode } from 'react';
import { AdminHeader } from '@/components/admin-header';
import { LogoInput } from '@/components/logo-input';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardFooter, CardHeader, CardTitle } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import { Field, FieldError } from '@/components/ui/field';
import { Input } from '@/components/ui/input';
import { NativeSelect } from '@/components/ui/native-select';
import AppLayout from '@/layouts/AppLayout';

type Detail = 'name' | 'division' | 'subdivision' | 'website';

// Mirrors App\Http\Controllers\Admin\SiteSettingsController@edit.
type SiteSettingsProps = {
    settings: {
        name: string;
        division: string | null;
        subdivision: string | null;
        website: string | null;
        logo: string | null;
        theme: string;
        autoController: boolean;
    };
    // Details set in the server's environment, with the variable each comes from.
    locked: Partial<Record<Detail, string>>;
    // Each App\Enums\Theme, with its name.
    themes: { value: string; label: string }[];
    // The Integration feature handing out the Controller role instead, if one is.
    controllerSource: string | null;
};

export default function SiteSettings({ settings, locked, themes, controllerSource }: SiteSettingsProps) {
    const form = useForm({
        name: settings.name,
        division: settings.division ?? '',
        subdivision: settings.subdivision ?? '',
        website: settings.website ?? '',
        auto_controller: settings.autoController,
        logo: null as File | null,
        remove_logo: false,
        theme: settings.theme,
    });

    const { data, errors } = form;

    const submit = (e: FormEvent) => {
        e.preventDefault();
        form.post('/admin/settings', {
            preserveScroll: true,
            onSuccess: () => form.setData((values) => ({ ...values, logo: null, remove_logo: false })),
        });
    };

    const hint = (detail: Detail, fallback?: string) =>
        locked[detail] ? (
            <span className="flex items-center gap-1.5 text-xs font-normal text-muted-foreground">
                <Lock className="size-3" aria-hidden />
                Set by the server ({locked[detail]})
            </span>
        ) : (
            fallback && <span className="text-xs font-normal text-muted-foreground">{fallback}</span>
        );

    return (
        <>
            <Head title="Settings · Admin" />

            <AdminHeader title="Settings" description="Who this Event Hub belongs to: the vACC’s details, logo and colors, shown across the site." />

            <form onSubmit={submit} className="mx-auto flex max-w-3xl flex-col gap-8 px-4 py-8 sm:px-6">
                <Card>
                    <CardHeader>
                        <CardTitle>vACC</CardTitle>
                        <CardDescription>
                            Details set on the server can’t be changed here. Keys and secrets for VATSIM and other services are never shown in the
                            app.
                        </CardDescription>
                    </CardHeader>
                    <CardContent className="flex flex-col gap-5">
                        <Field label="Name" error={errors.name}>
                            <Input
                                value={data.name}
                                onChange={(e) => form.setData('name', e.target.value)}
                                aria-invalid={!!errors.name}
                                disabled={!!locked.name}
                                maxLength={80}
                                required
                            />
                            {hint('name')}
                        </Field>
                        <Field label="Division" error={errors.division}>
                            <Input
                                value={data.division}
                                onChange={(e) => form.setData('division', e.target.value)}
                                aria-invalid={!!errors.division}
                                disabled={!!locked.division}
                                maxLength={80}
                                placeholder="VATEUD"
                            />
                            {hint('division')}
                        </Field>
                        <Field label="Subdivision" error={errors.subdivision}>
                            <Input
                                value={data.subdivision}
                                onChange={(e) => form.setData('subdivision', e.target.value.toUpperCase())}
                                aria-invalid={!!errors.subdivision}
                                disabled={!!locked.subdivision}
                                maxLength={10}
                                placeholder="SCA"
                                className="font-mono"
                            />
                            {hint('subdivision', 'The code VATSIM uses for your vACC.')}
                        </Field>
                        <Field label="Website" error={errors.website}>
                            <Input
                                type="url"
                                value={data.website}
                                onChange={(e) => form.setData('website', e.target.value)}
                                aria-invalid={!!errors.website}
                                disabled={!!locked.website}
                                placeholder="https://"
                            />
                            {hint('website')}
                        </Field>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle>Controllers</CardTitle>
                        <CardDescription>Only people with the Controller role can book shifts, show interest and be rostered.</CardDescription>
                    </CardHeader>
                    <CardContent className="flex flex-col gap-2">
                        {controllerSource && (
                            <p className="rounded-lg bg-muted px-3 py-2 text-sm">
                                {controllerSource} hands out the Controller role, so this rule is set aside. Change it on the{' '}
                                <Link href="/admin/integrations" className="font-medium underline underline-offset-4">
                                    Integrations
                                </Link>{' '}
                                page.
                            </p>
                        )}
                        <div className="flex items-start gap-3">
                            <Checkbox
                                id="auto-controller"
                                className="mt-0.5"
                                disabled={!!controllerSource}
                                checked={data.auto_controller}
                                onCheckedChange={(checked) => form.setData('auto_controller', checked === true)}
                            />
                            <label htmlFor="auto-controller" className="flex flex-col gap-0.5">
                                <span className="font-medium">Give the Controller role automatically</span>
                                <span className="text-sm text-muted-foreground">
                                    Everyone rated S1 or above in your subdivision gets Controller when they sign in, and loses it when they leave the
                                    subdivision or lose their rating. Controller roles you give by hand are left alone.
                                </span>
                            </label>
                        </div>
                        <FieldError message={errors.auto_controller} />
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle>Branding</CardTitle>
                    </CardHeader>
                    <CardContent className="flex flex-col gap-6">
                        <LogoInput
                            current={data.remove_logo ? null : settings.logo}
                            file={data.logo}
                            error={errors.logo}
                            onChange={(file) => form.setData((values) => ({ ...values, logo: file, remove_logo: false }))}
                            onRemove={() =>
                                form.setData((values) => ({ ...values, logo: null, remove_logo: values.logo ? values.remove_logo : true }))
                            }
                        />
                        <Field label="Theme" error={errors.theme}>
                            <NativeSelect value={data.theme} onChange={(e) => form.setData('theme', e.target.value)}>
                                {themes.map((theme) => (
                                    <option key={theme.value} value={theme.value}>
                                        {theme.label}
                                    </option>
                                ))}
                            </NativeSelect>
                            <span className="text-xs font-normal text-muted-foreground">
                                The colors the whole site is shown in, from the navbar and cards to buttons and the front page.
                            </span>
                        </Field>
                    </CardContent>
                    <CardFooter className="justify-end">
                        <Button type="submit" disabled={form.processing}>
                            Save settings
                        </Button>
                    </CardFooter>
                </Card>
            </form>
        </>
    );
}

SiteSettings.layout = (page: ReactNode) => <AppLayout>{page}</AppLayout>;
