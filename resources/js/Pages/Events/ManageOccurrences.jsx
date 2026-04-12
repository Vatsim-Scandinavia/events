import { useState } from 'react';
import { Link, router, usePage, Head } from '@inertiajs/react';
import Layout from '../../Layouts/Layout';
import Button from '../../Components/Button';
import { format, parseISO, isPast } from 'date-fns';
import { TimeDisplay } from '../../Components/DateTimeDisplay';

export default function ManageOccurrences({ event, occurrences }) {
    const { auth } = usePage().props;
    const [processing, setProcessing] = useState({});

    const handleToggleOccurrence = (occurrenceDate, isCancelling) => {
        if (isCancelling && !confirm('Are you sure you want to cancel this occurrence? Staffing bookings remain but the event will be hidden from public listings.')) {
            return;
        }

        const endpoint = isCancelling ? 'cancel-occurrence' : 'uncancel-occurrence';

        setProcessing(prev => ({ ...prev, [occurrenceDate]: true }));

        router.post(
            `/events/${event.id}/${endpoint}`,
            { occurrence_date: occurrenceDate },
            {
                onFinish: () => setProcessing(prev => ({ ...prev, [occurrenceDate]: false })),
                preserveScroll: true,
            }
        );
    };

    return (
        <>
            <Head title={`Manage Occurrences - ${event.title}`} />
            <Layout auth={auth} className="">
                {/* Back link + page title */}
                <div className="mb-6">
                    <Link
                        href={`/events/${event.id}`}
                        className="text-sm text-secondary dark:text-primary hover:underline inline-flex items-center gap-1 mb-2"
                    >
                        <span>←</span> Back to Event
                    </Link>
                    <h1 className="text-2xl font-semibold text-neutral-900 dark:text-neutral-100">{event.title}</h1>
                </div>

                {/* Card */}
                <div className="border border-neutral-200 dark:border-neutral-700">

                    {/* Card Header */}
                    <div className="bg-secondary dark:bg-neutral-800 border-b border-neutral-200 dark:border-neutral-700 px-6 py-4">
                        <h2 className="text-lg font-semibold text-white">Manage Occurrences</h2>
                        <p className="text-sm text-white/70 mt-0.5">
                            Changes here affect individual dates. To change the whole series, edit the main event.
                        </p>
                    </div>

                    {/* Occurrences list */}
                    <div className="bg-white dark:bg-neutral-800 divide-y divide-neutral-200 dark:divide-neutral-700">
                        {occurrences.length === 0 ? (
                            <p className="text-center text-neutral-500 dark:text-neutral-400 py-12">
                                No future occurrences found for the next 12 months.
                            </p>
                        ) : (
                            occurrences.map((occ) => {
                                const startDate = parseISO(occ.start);
                                const isOccPast = isPast(startDate);
                                const isProcessing = processing[occ.start];

                                return (
                                    <div
                                        key={occ.start}
                                        className={`flex justify-between items-center px-6 py-4 transition-colors ${
                                            occ.cancelled
                                                ? 'bg-red-50 dark:bg-red-950/20'
                                                : 'hover:bg-neutral-50 dark:hover:bg-neutral-700/30'
                                        }`}
                                    >
                                        <div className="flex-1">
                                            <div className="flex items-center gap-3">
                                                <span className={`font-medium ${
                                                    occ.cancelled
                                                        ? 'text-red-600 dark:text-red-400 line-through'
                                                        : isOccPast
                                                            ? 'text-neutral-400 dark:text-neutral-500'
                                                            : 'text-neutral-900 dark:text-neutral-100'
                                                }`}>
                                                    {format(startDate, 'EEEE, MMMM d, yyyy')}
                                                </span>

                                                {occ.cancelled && (
                                                    <span className="px-2 py-0.5 text-[10px] font-bold tracking-wider bg-red-600 text-white uppercase">
                                                        Cancelled
                                                    </span>
                                                )}
                                                {isOccPast && !occ.cancelled && (
                                                    <span className="px-2 py-0.5 text-[10px] font-bold tracking-wider bg-neutral-400 text-white uppercase">
                                                        Past
                                                    </span>
                                                )}
                                            </div>

                                            <div className="text-sm text-neutral-500 dark:text-neutral-400 mt-0.5">
                                                <TimeDisplay datetime={occ.start} /> – <TimeDisplay datetime={occ.end} />
                                            </div>
                                        </div>

                                        <div className="ml-4">
                                            <Button
                                                variant={occ.cancelled ? 'success' : 'danger'}
                                                size="sm"
                                                onClick={() => handleToggleOccurrence(occ.start, !occ.cancelled)}
                                                disabled={isProcessing}
                                                className="min-w-25 justify-center"
                                            >
                                                {isProcessing ? '...' : (occ.cancelled ? 'Restore' : 'Cancel')}
                                            </Button>
                                        </div>
                                    </div>
                                );
                            })
                        )}
                    </div>
                </div>
            </Layout>
        </>
    );
}