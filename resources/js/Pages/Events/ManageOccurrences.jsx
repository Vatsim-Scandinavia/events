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
            <Layout auth={auth}>
                <div className="max-w-5xl mx-auto px-4 sm:px-6">
                    <div className="mb-6">
                        <Link 
                            href={`/events/${event.id}`} 
                            className="text-sm text-secondary dark:text-primary hover:underline mb-2 inline-flex items-center gap-1"
                        >
                            <span>←</span> Back to Event
                        </Link>
                        <h1 className="text-3xl font-bold text-secondary dark:text-primary">Manage Occurrences</h1>
                        <p className="text-gray-600 dark:text-dark-text-secondary mt-1">{event.title}</p>
                    </div>

                    <div className="bg-white dark:bg-dark-bg-secondary overflow-hidden" style={{ boxShadow: 'var(--shadow-card)' }}>
                        <div className="bg-grey-100 dark:bg-dark-bg-tertiary px-6 py-4 border-b-2 border-grey-200 dark:border-dark-border">
                            <h2 className="text-xl font-semibold text-grey-900 dark:text-dark-text">All Occurrences</h2>
                            <p className="text-sm text-gray-600 dark:text-dark-text-secondary mt-1">
                                Changes here affect individual dates. To change the whole series, edit the main event.
                            </p>
                        </div>

                        <div className="divide-y divide-grey-200 dark:divide-dark-border">
                            {occurrences.length === 0 ? (
                                <p className="text-center text-gray-500 dark:text-dark-text-secondary py-12">
                                    No future occurrences found for the next 12 months.
                                </p>
                            ) : (
                                occurrences.map((occ, index) => {
                                    // occ.start is already an ISO string from our Service
                                    const startDate = parseISO(occ.start);
                                    const isOccPast = isPast(startDate);
                                    const isProcessing = processing[occ.start];

                                    return (
                                        <div
                                            key={occ.start}
                                            className={`flex justify-between items-center p-4 transition-colors ${
                                                occ.cancelled 
                                                    ? 'bg-red-50 dark:bg-red-950/20' 
                                                    : 'hover:bg-grey-50 dark:hover:bg-dark-bg-tertiary'
                                            }`}
                                        >
                                            <div className="flex-1">
                                                <div className="flex items-center gap-3">
                                                    <span className={`text-lg font-medium ${
                                                        occ.cancelled 
                                                            ? 'text-red-700 dark:text-red-400 line-through' 
                                                            : isOccPast ? 'text-gray-400' : 'text-grey-900 dark:text-dark-text'
                                                    }`}>
                                                        {format(startDate, 'EEEE, MMMM d, yyyy')}
                                                    </span>
                                                    
                                                    {occ.cancelled && (
                                                        <span className="px-2 py-0.5 text-[10px] font-bold tracking-wider bg-red-600 text-white uppercase">
                                                            Cancelled
                                                        </span>
                                                    )}
                                                    {isOccPast && !occ.cancelled && (
                                                        <span className="px-2 py-0.5 text-[10px] font-bold tracking-wider bg-gray-400 text-white uppercase">
                                                            Past
                                                        </span>
                                                    )}
                                                </div>
                                                
                                                <div className="text-sm text-grey-600 dark:text-dark-text-secondary mt-0.5">
                                                    <TimeDisplay datetime={occ.start} /> - <TimeDisplay datetime={occ.end} />
                                                </div>
                                            </div>

                                            <div className="ml-4">
                                                <Button
                                                    variant={occ.cancelled ? "success" : "danger"}
                                                    size="sm"
                                                    onClick={() => handleToggleOccurrence(occ.start, !occ.cancelled)}
                                                    disabled={isProcessing}
                                                    className="min-w-[100px] justify-center"
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
                </div>
            </Layout>
        </>
    );
}