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
import type { HeroGradient } from '@/types/inertia';

// Where the hero's gradient starts when an admin first turns it on.
const defaultHeroGradient: HeroGradient = { from: '#2563eb', to: '#9333ea' };

// Mirrors App\Enums\Theme.
type Theme = 'default' | 'brand';

type BrandColors = { primary: string; accent: string };

// Where the Brand colors start when an admin first picks the Brand theme.
const defaultBrandColors: BrandColors = { primary: '#2563eb', accent: '#1e3a8a' };

type Detail = 'name' | 'division' | 'subdivision' | 'website';

// Mirrors App\Http\Controllers\Admin\SiteSettingsController@edit.
type SiteSettingsProps = {
    settings: {
        name: string;
        division: string | null;
        subdivision: string | null;
        website: string | null;
        logo: string | null;
        heroGradient: HeroGradient | null;
        theme: Theme;
        // Kept while the Default theme is in use, so switching back brings them back.
        brandColors: BrandColors | null;
        autoController: boolean;
    };
    // Details set in the server's environment, with the variable each comes from.
    locked: Partial<Record<Detail, string>>;
    // The Integration feature handing out the Controller role instead, if one is.
    controllerSource: string | null;
};

export default function SiteSettings({ settings, locked, controllerSource }: SiteSettingsProps) {
    const form = useForm({
        name: settings.name,
        division: settings.division ?? '',
        subdivision: settings.subdivision ?? '',
        website: settings.website ?? '',
        auto_controller: settings.autoController,
        logo: null as File | null,
        remove_logo: false,
        hero_gradient: settings.heroGradient,
        theme: settings.theme,
        brand_colors: settings.brandColors,
    });

    const { data, errors } = form;
    const heroGradient = data.hero_gradient;
    const brandColors = data.brand_colors;

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
                        <div className="flex flex-col gap-4">
                            <Field label="Theme" error={errors.theme}>
                                <NativeSelect
                                    value={data.theme}
                                    onChange={(e) => {
                                        const theme = e.target.value as Theme;
                                        form.setData((values) => ({
                                            ...values,
                                            theme,
                                            brand_colors: theme === 'brand' ? (values.brand_colors ?? defaultBrandColors) : values.brand_colors,
                                        }));
                                    }}
                                >
                                    <option value="default">Default</option>
                                    <option value="brand">Brand</option>
                                </NativeSelect>
                                <span className="text-xs font-normal text-muted-foreground">
                                    The colors the whole site is shown in. Brand uses your own colors for buttons, links and highlights.
                                </span>
                            </Field>
                            {data.theme === 'brand' && brandColors && (
                                <div className="grid gap-4 sm:grid-cols-2">
                                    <Field label="Primary color" error={errors['brand_colors.primary']}>
                                        <Input
                                            type="color"
                                            className="h-10 w-20 p-1"
                                            value={brandColors.primary}
                                            onChange={(e) => form.setData('brand_colors', { ...brandColors, primary: e.target.value })}
                                        />
                                        <span className="text-xs font-normal text-muted-foreground">Buttons, links and focus outlines.</span>
                                    </Field>
                                    <Field label="Accent color" error={errors['brand_colors.accent']}>
                                        <Input
                                            type="color"
                                            className="h-10 w-20 p-1"
                                            value={brandColors.accent}
                                            onChange={(e) => form.setData('brand_colors', { ...brandColors, accent: e.target.value })}
                                        />
                                        <span className="text-xs font-normal text-muted-foreground">
                                            Hover and selected states, and secondary buttons.
                                        </span>
                                    </Field>
                                </div>
                            )}
                        </div>
                        <div className="flex flex-col gap-4">
                            <div className="flex items-start gap-3">
                                <Checkbox
                                    id="hero-gradient"
                                    className="mt-0.5"
                                    checked={heroGradient !== null}
                                    onCheckedChange={(checked) => form.setData('hero_gradient', checked === true ? defaultHeroGradient : null)}
                                />
                                <label htmlFor="hero-gradient" className="flex flex-col gap-0.5">
                                    <span className="font-medium">Custom hero gradient</span>
                                    <span className="text-sm text-muted-foreground">
                                        Tints the top of the front page with your colors. It stays light enough to read the text on.
                                    </span>
                                </label>
                            </div>
                            {heroGradient && (
                                <div className="grid gap-4 sm:grid-cols-2">
                                    <Field label="Start color" error={errors['hero_gradient.from']}>
                                        <Input
                                            type="color"
                                            className="h-10 w-20 p-1"
                                            value={heroGradient.from}
                                            onChange={(e) => form.setData('hero_gradient', { ...heroGradient, from: e.target.value })}
                                        />
                                    </Field>
                                    <Field label="End color" error={errors['hero_gradient.to']}>
                                        <Input
                                            type="color"
                                            className="h-10 w-20 p-1"
                                            value={heroGradient.to}
                                            onChange={(e) => form.setData('hero_gradient', { ...heroGradient, to: e.target.value })}
                                        />
                                    </Field>
                                </div>
                            )}
                        </div>
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
