import { useState } from 'react';
import { Link, router, usePage, Head } from '@inertiajs/react';
import Layout from '../../Layouts/Layout';
import Button from '../../Components/Button';
import { format } from 'date-fns';
import DateTimeDisplay, { TimeDisplay } from '../../Components/DateTimeDisplay';

export default function ManageOccurrences({ event, occurrences }) {
    const { auth } = usePage().props;
    const [processing, setProcessing] = useState({});

    const handleCancelOccurrence = (occurrenceDate) => {
        if (!confirm('Are you sure you want to cancel this occurrence? Existing staffing bookings will remain but the event will not appear in public listings.')) {
            return;
        }

        setProcessing(prev => ({ ...prev, [occurrenceDate]: true }));

        router.post(
            `/events/${event.id}/cancel-occurrence`,
            { occurrence_date: occurrenceDate },
            {
                onFinish: () => {
                    setProcessing(prev => ({ ...prev, [occurrenceDate]: false }));
                },
                preserveScroll: true,
            }
        );
    };

    const handleUncancelOccurrence = (occurrenceDate) => {
        setProcessing(prev => ({ ...prev, [occurrenceDate]: true }));

        router.post(
            `/events/${event.id}/uncancel-occurrence`,
            { occurrence_date: occurrenceDate },
            {
                onFinish: () => {
                    setProcessing(prev => ({ ...prev, [occurrenceDate]: false }));
                },
                preserveScroll: true,
            }
        );
    };

    return (
        <>
            <Head title={`Manage Occurrences - ${event.title}`} />
            <Layout auth={auth}>
                <div className="max-w-5xl mx-auto">
                <div className="mb-6">
                    <div className="flex items-center justify-between">
                        <div>
                            <Link 
                                href={`/events/${event.id}`} 
                                className="text-sm text-secondary dark:text-primary hover:underline mb-2 inline-block"
                            >
                                ← Back to Event
                            </Link>
                            <h1 className="text-3xl font-bold text-secondary dark:text-primary">Manage Occurrences</h1>
                            <p className="text-gray-600 dark:text-dark-text-secondary mt-2">{event.title}</p>
                        </div>
                    </div>
                </div>

                <div className="bg-white dark:bg-dark-bg-secondary" style={{ boxShadow: 'var(--shadow-card)' }}>
                    <div className="bg-grey-100 dark:bg-dark-bg-tertiary px-6 py-4 border-b-2 border-grey-200 dark:border-dark-border">
                        <h2 className="text-xl font-semibold text-grey-900 dark:text-dark-text">All Occurrences</h2>
                        <p className="text-sm text-gray-600 dark:text-dark-text-secondary mt-1">
                            Cancelled occurrences will not appear in public event listings but staffing data is preserved.
                        </p>
                    </div>

                    <div className="p-6">
                        {occurrences.length === 0 ? (
                            <p className="text-center text-gray-500 dark:text-dark-text-secondary py-8">No occurrences found.</p>
                        ) : (
                            <div className="space-y-2">
                                {occurrences.map((occurrence, index) => {
                                    const occurrenceDate = new Date(occurrence.start).toISOString();
                                    const isPast = new Date(occurrence.start) < new Date();
                                    const isProcessing = processing[occurrenceDate];

                                    return (
                                        <div
                                            key={index}
                                            className={`flex justify-between items-center py-4 px-4 ${
                                                occurrence.cancelled 
                                                    ? 'bg-red-50 border-2 border-red-200' 
                                                    : isPast 
                                                    ? 'bg-gray-50 border-2 border-gray-200'
                                                    : 'bg-grey-50 border-2 border-grey-200'
                                            }`}
                                        >
                                            <div className="flex-1">
                                                <div className="flex items-center gap-3">
                                                    <span className={`text-lg font-medium ${
                                                        occurrence.cancelled 
                                                            ? 'text-red-700 line-through' 
                                                            : isPast
                                                            ? 'text-gray-500'
                                                            : 'text-grey-900'
                                                    }`}>
                                                        {format(new Date(occurrence.start), 'EEEE, MMMM d, yyyy')}
                                                    </span>
                                                    {occurrence.cancelled && (
                                                        <span className="inline-flex items-center px-2 py-0.5 text-xs font-medium bg-red-600 text-white">
                                                            CANCELLED
                                                        </span>
                                                    )}
                                                    {isPast && !occurrence.cancelled && (
                                                        <span className="inline-flex items-center px-2 py-0.5 text-xs font-medium bg-gray-400 text-white">
                                                            PAST
                                                        </span>
                                                    )}
                                                </div>
                                                <div className={`text-sm mt-1 ${
                                                    occurrence.cancelled 
                                                        ? 'text-red-600' 
                                                        : isPast
                                                        ? 'text-gray-500'
                                                        : 'text-grey-600'
                                                }`}>
                                                    <TimeDisplay datetime={occurrence.start} /> - <TimeDisplay datetime={occurrence.end} />
                                                </div>
                                            </div>

                                            <div>
                                                {occurrence.cancelled ? (
                                                    <Button
                                                        variant="success"
                                                        size="sm"
                                                        onClick={() => handleUncancelOccurrence(occurrenceDate)}
                                                        disabled={isProcessing}
                                                    >
                                                        {isProcessing ? 'Processing...' : 'Restore'}
                                                    </Button>
                                                ) : (
                                                    <Button
                                                        variant="danger"
                                                        size="sm"
                                                        onClick={() => handleCancelOccurrence(occurrenceDate)}
                                                        disabled={isProcessing}
                                                    >
                                                        {isProcessing ? 'Processing...' : 'Cancel'}
                                                    </Button>
                                                )}
                                            </div>
                                        </div>
                                    );
                                })}
                            </div>
                        )}
                    </div>
                </div>
            </div>
        </Layout>
        </>
    );
}
