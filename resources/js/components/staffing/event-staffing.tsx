import { Link } from '@inertiajs/react';
import { Headset } from 'lucide-react';
import OpenInterest from '@/components/staffing/open-interest';
import ShiftBoard from '@/components/staffing/shift-board';
import { Button } from '@/components/ui/button';
import type { EventStaffing } from '@/types/staffing';

type Props = {
    // How links name the event, e.g. "42-copenhagen-fly-in".
    eventKey: string;
    staffing: Exclude<EventStaffing, { mode: 'none' }>;
};

/**
 * The controller-facing half of staffing on the event page: book shifts, or show
 * interest and see the published roster.
 */
export default function EventStaffing({ eventKey, staffing }: Props) {
    return (
        <section aria-labelledby="staffing" className="flex flex-col gap-4">
            <div className="flex flex-wrap items-center justify-between gap-3">
                <h2 id="staffing" className="flex items-center gap-2 text-xl font-semibold tracking-tight">
                    <span className="text-muted-foreground">
                        <Headset className="size-5" />
                    </span>
                    Controller staffing
                </h2>
                {staffing.canManage && (
                    <Button variant="outline" size="sm" asChild>
                        <Link href={`/events/${eventKey}/staffing?occurrence=${staffing.occurrence.id}`}>Manage staffing</Link>
                    </Button>
                )}
            </div>

            {staffing.mode === 'shifts' ? <ShiftBoard staffing={staffing} /> : <OpenInterest staffing={staffing} />}
        </section>
    );
}
