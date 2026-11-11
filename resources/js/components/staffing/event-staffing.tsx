import { Link } from '@inertiajs/react';
import { Headset } from 'lucide-react';
import SectionHeading from '@/components/events/section-heading';
import OpenInterest, { openInterestHint } from '@/components/staffing/open-interest';
import ShiftBoard, { shiftBoardHint } from '@/components/staffing/shift-board';
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
        <section aria-labelledby="staffing" className="flex flex-col gap-6">
            <SectionHeading
                id="staffing"
                icon={<Headset className="size-5" />}
                description={staffing.mode === 'shifts' ? shiftBoardHint(staffing) : openInterestHint(staffing)}
                action={
                    staffing.canManage && (
                        <Button variant="outline" size="sm" asChild>
                            <Link href={`/events/${eventKey}/staffing?occurrence=${staffing.occurrence.id}`}>Manage staffing</Link>
                        </Button>
                    )
                }
            >
                Controller staffing
            </SectionHeading>

            {staffing.mode === 'shifts' ? <ShiftBoard staffing={staffing} /> : <OpenInterest staffing={staffing} />}
        </section>
    );
}
