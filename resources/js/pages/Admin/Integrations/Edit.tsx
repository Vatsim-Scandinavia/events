import { Head, Link, router, useForm, usePage } from '@inertiajs/react';
import { ArrowLeft, Lock, PlugZap, RefreshCw } from 'lucide-react';
import type { FormEvent, ReactNode } from 'react';
import { IntegrationRunBadge } from '@/components/integration-run-badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardFooter, CardHeader, CardTitle } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import { Field, FieldError } from '@/components/ui/field';
import { Input } from '@/components/ui/input';
import { Textarea } from '@/components/ui/textarea';
import AppLayout from '@/layouts/AppLayout';
import type { IntegrationRunStatus } from '@/types/admin';

// Mirrors the integration prop of App\Http\Controllers\Admin\IntegrationController@edit.
type EditableIntegration = {
    key: string;
    name: string;
    description: string;
    enabled: boolean;
    // Whether any feature syncs on a schedule, and so has runs and a removal limit.
    syncs: boolean;
    removalLimit: number;
    settings: {
        key: string;
        label: string;
        description: string | null;
        secret: boolean;
        required: boolean;
        // Entered in a text box, one entry per line.
        multiline: boolean;
        // Never sent for secrets.
        value: string | null;
        isSet: boolean;
        // The environment variable it's set by, when the server sets it.
        lockedBy: string | null;
    }[];
    features: {
        key: string;
        name: string;
        description: string;
        enabled: boolean;
        // Switched on, in an Integration that is too.
        on: boolean;
        syncs: boolean;
        controllerSource: boolean;
        // Things it couldn't do, for admins to look at; only while it's on.
        needsAttention: string[];
    }[];
};

// Mirrors the runs prop of App\Http\Controllers\Admin\IntegrationController@edit.
type IntegrationRun = {
    id: number;
    feature: string;
    status: IntegrationRunStatus;
    added: number;
    updated: number;
    removed: number;
    message: string | null;
    warnings: string[];
    approvedBy: string | null;
    startedAt: string;
    canApprove: boolean;
};

const changes = (run: IntegrationRun) => `${run.added} added · ${run.updated} updated · ${run.removed} removed`;

export default function IntegrationEdit({ integration, runs }: { integration: EditableIntegration; runs: IntegrationRun[] }) {
    const { errors: pageErrors } = usePage().props;
    const form = useForm({
        enabled: integration.enabled,
        features: integration.features.filter((feature) => feature.enabled).map((feature) => feature.key),
        settings: Object.fromEntries(
            integration.settings.filter((setting) => !setting.lockedBy).map((setting) => [setting.key, setting.secret ? '' : (setting.value ?? '')]),
        ) as Record<string, string>,
        ...(integration.syncs ? { removal_limit: integration.removalLimit } : {}),
    });

    const { data, errors } = form;
    const settingError = (key: string) => (errors as Record<string, string | undefined>)[`settings.${key}`];

    const submit = (e: FormEvent) => {
        e.preventDefault();
        form.put(`/admin/integrations/${integration.key}`, {
            preserveScroll: true,
            onSuccess: () => form.setData('settings', { ...data.settings, ...secretsCleared() }),
        });
    };

    const secretsCleared = () =>
        Object.fromEntries(integration.settings.filter((setting) => setting.secret && !setting.lockedBy).map((setting) => [setting.key, '']));

    const toggleFeature = (key: string, checked: boolean) =>
        form.setData('features', checked ? [...data.features, key] : data.features.filter((feature) => feature !== key));

    const post = (url: string) => router.post(url, {}, { preserveScroll: true });

    return (
        <>
            <Head title={`${integration.name} · Integrations`} />

            <div className="mx-auto flex max-w-3xl flex-col gap-8 px-4 py-8 sm:px-6">
                <div className="flex flex-col gap-3">
                    <Button variant="ghost" size="sm" className="-ml-2.5 self-start" asChild>
                        <Link href="/admin/integrations">
                            <ArrowLeft />
                            All integrations
                        </Link>
                    </Button>
                    <h1 className="text-2xl font-semibold tracking-tight">{integration.name}</h1>
                    <p className="text-muted-foreground">{integration.description}</p>
                </div>

                <form onSubmit={submit} className="flex flex-col gap-8">
                    <Card>
                        <CardHeader>
                            <CardTitle>Connection</CardTitle>
                            <CardDescription>
                                Settings set on the server can’t be changed here. Secrets are never shown again once saved; leave one empty to keep
                                it.
                            </CardDescription>
                        </CardHeader>
                        <CardContent className="flex flex-col gap-5">
                            {integration.settings.map((setting) =>
                                setting.lockedBy ? (
                                    <Field key={setting.key} label={setting.label}>
                                        {setting.multiline ? (
                                            <Textarea value={setting.value ?? ''} className="font-mono" disabled />
                                        ) : (
                                            <Input value={setting.secret ? '••••••••' : (setting.value ?? '')} disabled />
                                        )}
                                        <span className="flex items-center gap-1.5 text-xs font-normal text-muted-foreground">
                                            <Lock className="size-3" aria-hidden />
                                            Set by the server ({setting.lockedBy})
                                        </span>
                                    </Field>
                                ) : (
                                    <Field key={setting.key} label={setting.label} error={settingError(setting.key)}>
                                        {setting.multiline ? (
                                            <Textarea
                                                value={data.settings[setting.key] ?? ''}
                                                onChange={(e) => form.setData('settings', { ...data.settings, [setting.key]: e.target.value })}
                                                aria-invalid={!!settingError(setting.key)}
                                                className="font-mono"
                                                rows={4}
                                            />
                                        ) : (
                                            <Input
                                                type={setting.secret ? 'password' : 'text'}
                                                autoComplete="off"
                                                value={data.settings[setting.key] ?? ''}
                                                onChange={(e) => form.setData('settings', { ...data.settings, [setting.key]: e.target.value })}
                                                aria-invalid={!!settingError(setting.key)}
                                                placeholder={setting.secret && setting.isSet ? 'Saved; leave empty to keep it' : undefined}
                                            />
                                        )}
                                        {setting.description && (
                                            <span className="text-xs font-normal text-muted-foreground">{setting.description}</span>
                                        )}
                                    </Field>
                                ),
                            )}
                        </CardContent>
                        <CardFooter className="flex-col items-start gap-2">
                            <Button type="button" variant="outline" size="sm" onClick={() => post(`/admin/integrations/${integration.key}/test`)}>
                                <PlugZap />
                                Test saved connection
                            </Button>
                            <FieldError message={pageErrors.connection} />
                        </CardFooter>
                    </Card>

                    <Card>
                        <CardHeader>
                            <CardTitle>Features</CardTitle>
                            <CardDescription>
                                Switching {integration.name} on tests the connection first. Each feature can then be switched on by itself.
                            </CardDescription>
                        </CardHeader>
                        <CardContent className="flex flex-col gap-4">
                            <div className="flex flex-col gap-2">
                                <div className="flex items-start gap-3">
                                    <Checkbox
                                        id="integration-enabled"
                                        className="mt-0.5"
                                        checked={data.enabled}
                                        onCheckedChange={(checked) => form.setData('enabled', checked === true)}
                                    />
                                    <label htmlFor="integration-enabled" className="flex flex-col gap-0.5">
                                        <span className="font-medium">Switch {integration.name} on</span>
                                        <span className="text-sm text-muted-foreground">
                                            Switched off, none of its features run, and nothing it gave is taken away.
                                        </span>
                                    </label>
                                </div>
                                <FieldError message={errors.enabled} />
                            </div>

                            <div className="flex flex-col gap-3 border-t pt-4">
                                {integration.features.map((feature) => (
                                    <div key={feature.key} className="flex items-start gap-3">
                                        <Checkbox
                                            id={`feature-${feature.key}`}
                                            className="mt-0.5"
                                            disabled={!data.enabled}
                                            checked={data.features.includes(feature.key)}
                                            onCheckedChange={(checked) => toggleFeature(feature.key, checked === true)}
                                        />
                                        <label htmlFor={`feature-${feature.key}`} className="flex flex-col gap-0.5">
                                            <span className="font-medium">{feature.name}</span>
                                            <span className="text-sm text-muted-foreground">{feature.description}</span>
                                            {feature.controllerSource && (
                                                <span className="text-sm text-muted-foreground">
                                                    Hands out the Controller role instead of the hub’s own rule. Switching it off turns the roles it
                                                    gave into ones given by hand.
                                                </span>
                                            )}
                                        </label>
                                    </div>
                                ))}
                                <FieldError message={errors.features} />
                            </div>

                            {integration.syncs && (
                                <Field label="Removal limit (%)" error={errors.removal_limit} className="border-t pt-4 sm:max-w-xs">
                                    <Input
                                        type="number"
                                        min={1}
                                        max={100}
                                        value={data.removal_limit ?? ''}
                                        onChange={(e) => form.setData('removal_limit', Number(e.target.value))}
                                        aria-invalid={!!errors.removal_limit}
                                    />
                                    <span className="text-xs font-normal text-muted-foreground">
                                        A sync that would remove more than this share of what it manages is held until you approve it.
                                    </span>
                                </Field>
                            )}
                        </CardContent>
                        <CardFooter className="justify-end">
                            <Button type="submit" disabled={form.processing}>
                                Save {integration.name}
                            </Button>
                        </CardFooter>
                    </Card>
                </form>

                {integration.features.some((feature) => feature.needsAttention.length > 0) && (
                    <Card>
                        <CardHeader>
                            <CardTitle>Needs attention</CardTitle>
                            <CardDescription>What {integration.name} couldn’t do. Fix it here or upstream, and it’s tried again.</CardDescription>
                        </CardHeader>
                        <CardContent className="flex flex-col gap-4">
                            {integration.features
                                .filter((feature) => feature.needsAttention.length > 0)
                                .map((feature) => (
                                    <div key={feature.key} className="flex flex-col gap-1.5 text-sm">
                                        <span className="font-medium">{feature.name}</span>
                                        <ul className="list-disc pl-5 text-muted-foreground">
                                            {feature.needsAttention.map((item) => (
                                                <li key={item}>{item}</li>
                                            ))}
                                        </ul>
                                    </div>
                                ))}
                        </CardContent>
                    </Card>
                )}

                {integration.syncs && (
                    <Card>
                        <CardHeader>
                            <CardTitle>Syncs</CardTitle>
                            <CardDescription>A sync that fails or is held changes nothing. Runs are kept for 90 days.</CardDescription>
                        </CardHeader>
                        <CardContent className="flex flex-col gap-4">
                            <div className="flex flex-wrap gap-2">
                                {integration.features
                                    .filter((feature) => feature.syncs && feature.on)
                                    .map((feature) => (
                                        <Button
                                            key={feature.key}
                                            type="button"
                                            variant="outline"
                                            size="sm"
                                            onClick={() => post(`/admin/integrations/${integration.key}/features/${feature.key}/sync`)}
                                        >
                                            <RefreshCw />
                                            Sync {feature.name} now
                                        </Button>
                                    ))}
                            </div>
                            <FieldError message={pageErrors.sync ?? pageErrors.run} />

                            {runs.length === 0 ? (
                                <p className="text-sm text-muted-foreground">No syncs yet.</p>
                            ) : (
                                <ul className="divide-y rounded-lg ring-1 ring-foreground/10">
                                    {runs.map((run) => (
                                        <li key={run.id} className="flex flex-col gap-1.5 px-3 py-3 text-sm">
                                            <span className="flex flex-wrap items-center gap-2">
                                                <span className="font-medium">{run.feature}</span>
                                                <IntegrationRunBadge status={run.status} />
                                                <span className="text-muted-foreground">{run.startedAt}</span>
                                            </span>
                                            {run.status === 'succeeded' && <span className="text-muted-foreground">{changes(run)}</span>}
                                            {run.message && <span>{run.message}</span>}
                                            {run.approvedBy && <span className="text-muted-foreground">Approved by {run.approvedBy}</span>}
                                            {run.warnings.length > 0 && (
                                                <ul className="list-disc pl-5 text-muted-foreground">
                                                    {run.warnings.map((warning) => (
                                                        <li key={warning}>{warning}</li>
                                                    ))}
                                                </ul>
                                            )}
                                            {run.canApprove && (
                                                <Button
                                                    type="button"
                                                    size="sm"
                                                    className="self-start"
                                                    onClick={() => post(`/admin/integrations/${integration.key}/runs/${run.id}/approve`)}
                                                >
                                                    Approve and sync again
                                                </Button>
                                            )}
                                        </li>
                                    ))}
                                </ul>
                            )}
                        </CardContent>
                    </Card>
                )}
            </div>
        </>
    );
}

IntegrationEdit.layout = (page: ReactNode) => <AppLayout>{page}</AppLayout>;
