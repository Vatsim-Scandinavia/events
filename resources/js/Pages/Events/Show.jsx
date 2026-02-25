import { Link, usePage, router, Head } from '@inertiajs/react';
import Layout from '../../Layouts/Layout';
import Button from '../../Components/Button';
import { format } from 'date-fns';
import DateTimeDisplay, { TimeDisplay } from '../../Components/DateTimeDisplay';
import ReactMarkdown from 'react-markdown';
import { TrashIcon, PencilIcon, ArrowPathIcon, UserIcon } from '@heroicons/react/24/solid';

export default function Show({ event, instances, bannerUrl }) {
    const { auth } = usePage().props;

    const canEdit = !!auth.user && (
        auth.user.permissions?.includes('edit-events') ||
        auth.user.id === event.created_by
    );

    const handleDelete = () => {
        if (confirm('Are you sure you want to delete this event?')) {
            router.delete(`/events/${event.id}`);
        }
    };

    return (
        <>
            <Head title={event.title} />
            <Layout auth={auth}>
                <div className="w-full max-w-7xl mx-auto px-4 md:px-8 py-10 flex flex-col gap-6">

                    {/* Page Header */}
                    <div className="flex flex-col sm:flex-row sm:items-start sm:justify-between gap-4">
                        <div>
                            <h1 className="text-3xl font-bold text-neutral-900 dark:text-neutral-100">
                                {event.title}
                            </h1>
                            {event.recurrence_rule && (
                                <span className="inline-flex items-center px-2 py-0.5 text-xs font-medium bg-neutral-600 dark:bg-neutral-700 text-white uppercase w-fit">
                                    <ArrowPathIcon className="w-3 h-3 mr-1" />
                                    Recurring
                                </span>
                            )}
                        </div>
                        {canEdit && (
                            <div className="flex flex-wrap gap-2">
                                {event.recurrence_rule && (
                                    <Link href={`/events/${event.id}/occurrences`}>
                                        <Button variant="secondary"><ArrowPathIcon className="w-3 h-3 mr-1" />Manage Occurrences</Button>
                                    </Link>
                                )}
                                <Link href={`/events/${event.id}/edit`}>
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

                    {/* Banner */}
                    {bannerUrl && (
                        <div className="w-full aspect-video overflow-hidden">
                            <img
                                src={bannerUrl}
                                alt={event.title}
                                className="w-full h-full object-contain"
                            />
                        </div>
                    )}

                    {/* Event Details Card */}
                    <div className="border border-neutral-200 dark:border-neutral-700">
                        <div className="bg-secondary dark:bg-neutral-800 border-b border-neutral-200 dark:border-neutral-700 px-6 py-4">
                            <h2 className="text-lg font-semibold text-white dark:text-neutral-100">Event Details</h2>
                        </div>
                        <div className="bg-white dark:bg-neutral-800 p-6">
                            <div className="grid grid-cols-1 sm:grid-cols-2 gap-6 mb-6">
                                <div>
                                    <h3 className="text-sm font-medium text-neutral-500 dark:text-neutral-400">Start Time</h3>
                                    <div className="mt-1 text-lg text-neutral-900 dark:text-neutral-100">
                                        <DateTimeDisplay datetime={event.display_datetime} />
                                    </div>
                                </div>
                                <div>
                                    <h3 className="text-sm font-medium text-neutral-500 dark:text-neutral-400">End Time</h3>
                                    <div className="mt-1 text-lg text-neutral-900 dark:text-neutral-100">
                                        <DateTimeDisplay datetime={event.next_active_end} />
                                    </div>
                                </div>
                                <div>
                                    <h3 className="text-sm font-medium text-neutral-500 dark:text-neutral-400">Calendar</h3>
                                    <p className="mt-1 text-neutral-900 dark:text-neutral-100">{event.calendar?.name}</p>
                                </div>
                                {event.featured_airports?.length > 0 && (
                                    <div>
                                        <h3 className="text-sm font-medium text-neutral-500 dark:text-neutral-400">Featured Airports</h3>
                                        <div className="mt-1 flex flex-wrap gap-2">
                                            {event.featured_airports.map((airport) => (
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
                                    <ReactMarkdown>{event.long_description}</ReactMarkdown>
                                </div>
                            </div>
                        </div>
                    </div>

                    {/* Recurring Occurrences */}
                    {event.recurrence_rule && instances?.length > 1 && (
                        <div className="border border-neutral-200 dark:border-neutral-700">
                            <div className="bg-secondary dark:bg-neutral-800 border-b border-neutral-200 dark:border-neutral-700 px-6 py-4">
                                <h2 className="text-lg font-semibold text-white dark:text-neutral-100">Following Occurrences</h2>
                            </div>
                            <div className="bg-white dark:bg-neutral-800 divide-y divide-neutral-200 dark:divide-neutral-700">
                                {instances.slice(1, 4).map((instance, index) => (
                                    <div
                                        key={index}
                                        className={`flex justify-between items-center px-6 py-3 ${instance.cancelled ? 'opacity-50' : ''}`}
                                    >
                                        <div className="flex items-center gap-2">
                                            <span className={`text-neutral-900 dark:text-neutral-100 ${instance.cancelled ? 'line-through' : ''}`}>
                                                {format(new Date(instance.start), 'PPP')}
                                            </span>
                                            {instance.cancelled && (
                                                <span className="inline-flex items-center px-2 py-0.5 text-xs font-medium bg-danger text-white">
                                                    CANCELLED
                                                </span>
                                            )}
                                        </div>
                                        <span className="text-sm text-neutral-500 dark:text-neutral-400">
                                            <TimeDisplay datetime={instance.start} /> – <TimeDisplay datetime={instance.end} />
                                        </span>
                                    </div>
                                ))}
                            </div>
                        </div>
                    )}

                    {/* Staffing */}
                    {auth.user?.permissions?.includes('manage-staffings') && event.recurrence_rule && (
                        <div className="border border-neutral-200 dark:border-neutral-700">
                            <div className="bg-secondary dark:bg-neutral-800 border-b border-neutral-200 dark:border-neutral-700 px-6 py-4">
                                <div className="flex justify-between items-center">
                                    <h2 className="text-lg font-semibold text-white dark:text-neutral-100">Staffing</h2>
                                    <Link href={`/events/${event.id}/staffings`}>
                                        <Button variant="secondary"><UserIcon className="w-3 h-3 mr-1" />Manage Staffing</Button>
                                    </Link>
                                </div>
                            </div>

                            {event.staffings?.length > 0 ? (
                                <div className="bg-white dark:bg-neutral-800 p-6 flex flex-col gap-6">
                                    {event.staffings.map((staffing) => (
                                        <div key={staffing.id}>
                                            <h4 className="font-semibold text-neutral-900 dark:text-neutral-100 mb-3">
                                                {staffing.name}
                                            </h4>
                                            <div className="border border-neutral-200 dark:border-neutral-700 divide-y divide-neutral-200 dark:divide-neutral-700">
                                                {staffing.positions?.map((position) => (
                                                    <div
                                                        key={position.id}
                                                        className="flex justify-between items-center py-3 px-4 bg-neutral-50 dark:bg-neutral-700/30"
                                                    >
                                                        <div className="flex gap-4 items-center">
                                                            <div className="flex items-center gap-2">
                                                                <span className="font-mono text-sm font-semibold text-secondary dark:text-primary">
                                                                    {position.position_id}
                                                                </span>
                                                                {position.is_local && (
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
                                                                        {position.start_time && <TimeDisplay datetime={position.start_time} />}
                                                                        {position.start_time && position.end_time && <span> – </span>}
                                                                        {position.end_time && <TimeDisplay datetime={position.end_time} />}
                                                                    </div>
                                                                )}
                                                            </div>
                                                        </div>
                                                        {(position.booked_by || position.vatsim_cid) ? (
                                                            <span className="text-sm text-success font-medium">
                                                                Booked by {position.booked_by?.name ?? position.vatsim_cid}
                                                            </span>
                                                        ) : (
                                                            <span className="text-sm text-neutral-400 dark:text-neutral-500 font-medium">
                                                                Available
                                                            </span>
                                                        )}
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
                        </div>
                    )}

                </div>
            </Layout>
        </>
    );
}