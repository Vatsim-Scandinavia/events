import { Link, usePage, router, Head } from '@inertiajs/react';
import Layout from '../../Layouts/Layout';
import Button from '../../Components/Button';
import Card from '../../Components/Card';
import { format } from 'date-fns';
import DateTimeDisplay, { TimeDisplay } from '../../Components/DateTimeDisplay';
import ReactMarkdown from 'react-markdown';
import { TrashIcon, PencilIcon, ArrowPathIcon, UserIcon, ExclamationTriangleIcon } from '@heroicons/react/24/solid';

export default function Show({ event }) {
    const { auth } = usePage().props;
    const evt = event.data ?? event;

    const canManage = auth.user?.permissions?.includes('manage events');
    
    const handleDelete = () => {
        if (confirm('Are you sure you want to delete this event?')) {
            router.delete(`/events/${evt.slug}`);
        }
    };

    return (
        <>
            <Head title={evt.title} />
            <Layout auth={auth}>
                {/* Page Header */}
                <div className="flex flex-col sm:flex-row sm:items-start sm:justify-between gap-4">
                    <div>
                        <h1 className="text-3xl font-bold text-neutral-900 dark:text-neutral-100">
                            {evt.title}
                        </h1>
                        {evt.recurrence_rule && (
                            <span className="inline-flex items-center px-2 py-0.5 text-xs font-medium bg-neutral-600 dark:bg-neutral-700 text-white uppercase w-fit">
                                <ArrowPathIcon className="w-3 h-3 mr-1" />
                                Recurring
                            </span>
                        )}
                    </div>
                    {canManage && (
                        <div className="flex flex-wrap gap-2">
                            {evt.recurrence_rule && (
                                <Link href={`/events/${evt.slug}/occurrences`}>
                                    <Button variant="secondary"><ArrowPathIcon className="w-3 h-3 mr-1" />Manage Occurrences</Button>
                                </Link>
                            )}
                            <Link href={`/events/${evt.slug}/edit`}>
                                <Button variant="secondary">
                                    <PencilIcon className="w-3 h-3 mr-1" />
                                    Edit
                                </Button>
                            </Link>
                            <Button variant="danger" onClick={handleDelete}>
                                <TrashIcon className="w-4 h-4" />
                            </Button>
                        </div>
                    )}
                </div>

                {/* Cancelled next-occurrence warning (admins only) */}
                {canManage && evt.occurrences?.[0]?.status === 'cancelled' && (
                    <div className="flex items-start gap-3 border border-amber-400 bg-amber-50 dark:bg-amber-950/30 dark:border-amber-600 px-4 py-3 text-amber-800 dark:text-amber-300">
                        <ExclamationTriangleIcon className="w-5 h-5 shrink-0 mt-0.5" />
                        <div className="text-sm">
                            <span className="font-semibold">Next occurrence is cancelled</span>
                            {' '}(<DateTimeDisplay datetime={evt.occurrences[0].start_time} />).
                            {' '}
                            <Link href={`/events/${evt.slug}/occurrences`} className="underline hover:no-underline">
                                Restore it from Manage Occurrences.
                            </Link>
                        </div>
                    </div>
                )}

                {/* Banner */}
                {evt.banner_url && (
                    <div className="w-full aspect-video overflow-hidden">
                        <img
                            src={evt.banner_url}
                            alt={evt.title}
                            className="w-full h-full object-contain"
                        />
                    </div>
                )}

                {/* Event Details Card */}
                <Card title="Event Details">
                    <div className="bg-white dark:bg-neutral-800 p-6">
                        <div className="grid grid-cols-1 sm:grid-cols-2 gap-6 mb-6">
                            <div>
                                <h3 className="text-sm font-medium text-neutral-500 dark:text-neutral-400">Start Time</h3>
                                <div className="mt-1 text-lg text-neutral-900 dark:text-neutral-100">
                                    <DateTimeDisplay datetime={evt.occurrences?.[0]?.start_time} />
                                </div>
                            </div>
                            <div>
                                <h3 className="text-sm font-medium text-neutral-500 dark:text-neutral-400">End Time</h3>
                                <div className="mt-1 text-lg text-neutral-900 dark:text-neutral-100">
                                    <DateTimeDisplay datetime={evt.occurrences?.[0]?.end_time} />
                                </div>
                            </div>
                            <div>
                                <h3 className="text-sm font-medium text-neutral-500 dark:text-neutral-400">Calendar</h3>
                                <p className="mt-1 text-neutral-900 dark:text-neutral-100">{evt.calendar.title}</p>
                            </div>
                            {evt.featured_airports?.length > 0 && (
                                <div>
                                    <h3 className="text-sm font-medium text-neutral-500 dark:text-neutral-400">Featured Airports</h3>
                                    <div className="mt-1 flex flex-wrap gap-2">
                                        {evt.featured_airports.map((airport) => (
                                            <span
                                                key={airport}
                                                className="inline-flex items-center px-2.5 py-0.5 text-xs font-mono font-medium bg-secondary text-white"
                                            >
                                                {airport}
                                            </span>
                                        ))}
                                    </div>
                                </div>
                            )}
                        </div>

                        <div className="border-t border-neutral-200 dark:border-neutral-700 pt-6">
                            <h3 className="text-base font-medium text-neutral-900 dark:text-neutral-100 mb-3">Description</h3>
                            <div className="prose prose-sm max-w-none text-neutral-700 dark:text-neutral-300">
                                <ReactMarkdown>{evt.long_description}</ReactMarkdown>
                            </div>
                        </div>
                    </div>
                </Card>

                {/* Recurring Occurrences */}
                {evt.recurrence_rule && evt.occurrences?.length > 1 && (
                    <Card title="Following Occurrences" subtitle="Upcoming dates in this series">
                        <div className="bg-white dark:bg-neutral-800 divide-y divide-neutral-200 dark:divide-neutral-700">
                            {evt.occurrences.slice(1, 4).map((occurrence) => {
                                const cancelled = occurrence.status === 'cancelled';
                                return (
                                    <div
                                        key={occurrence.id}
                                        className={`flex justify-between items-center px-6 py-3 ${cancelled ? 'opacity-50' : ''}`}
                                    >
                                        <div className="flex items-center gap-2">
                                            <span className="text-neutral-900 dark:text-neutral-100">
                                                {format(new Date(occurrence.start_time), 'PPP')}
                                            </span>
                                            {cancelled && (
                                                <span className="inline-flex items-center px-2 py-0.5 text-xs font-medium bg-danger text-white">
                                                    CANCELLED
                                                </span>
                                            )}
                                        </div>
                                        <span className="text-sm text-neutral-500 dark:text-neutral-400">
                                            <TimeDisplay datetime={occurrence.start_time} /> – <TimeDisplay datetime={occurrence.end_time} />
                                        </span>
                                    </div>
                                );
                            })}
                        </div>
                    </Card>
                )}

                {canManage && (
                    <Card
                        title="Staffing"
                        actions={
                            <Link href={`/events/${evt.slug}/staffing`}>
                                <Button variant="secondary"><UserIcon className="w-3 h-3 mr-1" />Manage Staffing</Button>
                            </Link>
                        }
                    >
                        {evt.staffing?.sections?.length > 0 ? (
                            <div className="bg-white dark:bg-neutral-800 p-6 flex flex-col gap-6">
                                {evt.staffing.sections.map((section) => (
                                    <div key={section.id}>
                                        <h4 className="font-semibold text-neutral-900 dark:text-neutral-100 mb-3">
                                            {section.title}
                                        </h4>
                                        <div className="border border-neutral-200 dark:border-neutral-700 divide-y divide-neutral-200 dark:divide-neutral-700">
                                            {section.positions?.map((position) => (
                                                <div
                                                    key={position.id}
                                                    className="flex justify-between items-center py-3 px-4 bg-neutral-50 dark:bg-neutral-700/30"
                                                >
                                                    <div className="flex gap-4 items-center">
                                                        <div className="flex items-center gap-2">
                                                            <span className="font-mono text-sm font-semibold text-secondary dark:text-primary">
                                                                {position.position_id}
                                                            </span>
                                                            {position.is_local_time && (
                                                                <span className="inline-flex items-center px-2 py-0.5 text-xs font-medium bg-warning text-white">
                                                                    LOCAL
                                                                </span>
                                                            )}
                                                        </div>
                                                        <div>
                                                            <span className="text-sm text-neutral-700 dark:text-neutral-300">
                                                                {position.position_name}
                                                            </span>
                                                            {(position.start_time || position.end_time) && (
                                                                <div className="text-xs text-neutral-500 dark:text-neutral-400 mt-0.5">
                                                                    {position.start_time}{position.start_time && position.end_time && ' – '}{position.end_time}
                                                                </div>
                                                            )}
                                                        </div>
                                                    </div>
                                                    <span className="text-sm text-neutral-400 dark:text-neutral-500 font-medium">
                                                        Available
                                                    </span>
                                                </div>
                                            ))}
                                        </div>
                                    </div>
                                ))}
                            </div>
                        ) : (
                            <div className="bg-white dark:bg-neutral-800 px-6 py-10 text-center">
                                <p className="text-sm text-neutral-500 dark:text-neutral-400">No staffing sections created yet.</p>
                            </div>
                        )}
                    </Card>
                )}
            </Layout>
        </>
    );
}