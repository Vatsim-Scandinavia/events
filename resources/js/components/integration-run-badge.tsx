import { Badge } from '@/components/ui/badge';
import type { IntegrationRunStatus } from '@/types/admin';

const labels: Record<IntegrationRunStatus, string> = {
    running: 'Running',
    succeeded: 'Succeeded',
    failed: 'Failed',
    held: 'Held for review',
    approved: 'Approved',
};

/**
 * How an Integration's sync run went, as a badge.
 */
export function IntegrationRunBadge({ status }: { status: IntegrationRunStatus }) {
    const variant = status === 'failed' ? 'destructive' : status === 'held' ? 'default' : status === 'succeeded' ? 'secondary' : 'outline';

    return <Badge variant={variant}>{labels[status]}</Badge>;
}
