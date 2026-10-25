import { Head, Link } from '@inertiajs/react';
import { Settings2 } from 'lucide-react';
import type { ReactNode } from 'react';
import { AdminHeader } from '@/components/admin-header';
import { IntegrationRunBadge } from '@/components/integration-run-badge';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/AppLayout';
import type { IntegrationRunStatus } from '@/types/admin';

// Mirrors the integrations prop of App\Http\Controllers\Admin\IntegrationController@index.
type IntegrationSummary = {
    key: string;
    name: string;
    description: string;
    enabled: boolean;
    // Names of the features doing their job.
    features: string[];
    lastRun: { status: IntegrationRunStatus; startedAt: string } | null;
    // Runs waiting for an Administrator's approval.
    held: number;
};

export default function IntegrationsIndex({ integrations }: { integrations: IntegrationSummary[] }) {
    return (
        <>
            <Head title="Integrations" />

            <AdminHeader
                title="Integrations"
                description="Connect the Event Hub to your vACC’s other systems. Everything works without them; switch on only what you use."
            />

            <div className="mx-auto flex max-w-6xl flex-col gap-4 px-4 py-8 sm:px-6">
                {integrations.length === 0 ? (
                    <p className="rounded-xl bg-card px-4 py-8 text-center text-muted-foreground ring-1 ring-foreground/10">
                        This Event Hub doesn’t offer any integrations yet.
                    </p>
                ) : (
                    <ul className="divide-y overflow-hidden rounded-xl bg-card ring-1 ring-foreground/10">
                        {integrations.map((integration) => (
                            <li key={integration.key} className="flex flex-col gap-3 px-4 py-4 sm:flex-row sm:items-center">
                                <div className="flex flex-1 flex-col gap-1.5">
                                    <span className="flex flex-wrap items-center gap-2 font-medium">
                                        {integration.name}
                                        <Badge variant={integration.enabled ? 'secondary' : 'outline'}>{integration.enabled ? 'On' : 'Off'}</Badge>
                                        {integration.held > 0 && (
                                            <Badge>
                                                {integration.held === 1 ? '1 sync held for review' : `${integration.held} syncs held for review`}
                                            </Badge>
                                        )}
                                    </span>
                                    <span className="text-sm text-muted-foreground">{integration.description}</span>
                                    {integration.enabled && (
                                        <span className="flex flex-wrap items-center gap-2 text-sm text-muted-foreground">
                                            {integration.features.length > 0 ? integration.features.join(' · ') : 'No features switched on'}
                                            {integration.lastRun && (
                                                <>
                                                    <span aria-hidden>·</span>
                                                    Last sync {integration.lastRun.startedAt}
                                                    <IntegrationRunBadge status={integration.lastRun.status} />
                                                </>
                                            )}
                                        </span>
                                    )}
                                </div>
                                <Button variant="outline" size="sm" className="self-start sm:self-center" asChild>
                                    <Link href={`/admin/integrations/${integration.key}`}>
                                        <Settings2 />
                                        Set up
                                    </Link>
                                </Button>
                            </li>
                        ))}
                    </ul>
                )}
            </div>
        </>
    );
}

IntegrationsIndex.layout = (page: ReactNode) => <AppLayout>{page}</AppLayout>;
