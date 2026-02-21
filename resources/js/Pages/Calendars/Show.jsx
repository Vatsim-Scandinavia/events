import { Link, usePage, router, Head } from '@inertiajs/react';
import Layout from '../../Layouts/Layout';
import Button from '../../Components/Button';
import DateTimeDisplay from '../../Components/DateTimeDisplay';
import { TrashIcon, PencilIcon, PlusIcon } from '@heroicons/react/24/solid';

export default function Show({ calendar }) {
    const { auth } = usePage().props;

    const canEdit = auth.user?.permissions?.includes('edit-calendars') ||
                    calendar.created_by === auth.user?.id ||
                    auth.user?.roles?.includes('admin');

    const canDelete = auth.user?.permissions?.includes('delete-calendars') ||
                      calendar.created_by === auth.user?.id ||
                      auth.user?.roles?.includes('admin');

    const handleDelete = () => {
        if (confirm('Are you sure you want to delete this calendar? This will also delete all events in this calendar.')) {
            router.delete(`/calendars/${calendar.id}`);
        }
    };

    return (
        <>
            <Head title={calendar.name} />
            <Layout auth={auth}>
                <div className="w-full max-w-7xl mx-auto px-4 md:px-8 py-10 flex flex-col gap-6">

                    {/* Calendar info card */}
                    <div className="border border-neutral-200 dark:border-neutral-700">

                        {/* Card Header */}
                        <div className="bg-secondary dark:bg-neutral-800 border-b border-neutral-200 dark:border-neutral-700 px-6 py-4">
                            <div className="flex justify-between items-center gap-4">
                                <div>
                                    <div className="flex items-center gap-2">
                                        <h1 className="text-lg font-semibold text-white">{calendar.name}</h1>
                                        {!calendar.is_public && (
                                            <span className="px-2 py-0.5 text-[10px] font-bold tracking-wider bg-warning text-white uppercase">
                                                Private
                                            </span>
                                        )}
                                    </div>
                                    <p className="text-sm text-white/70 mt-0.5">
                                        Created by {calendar.creator?.name || 'Unknown'}
                                    </p>
                                </div>
                                {auth.user && (canEdit || canDelete) && (
                                    <div className="flex gap-3">
                                        {canEdit && (
                                            <Link href={`/calendars/${calendar.id}/edit`}>
                                                <Button variant="secondary"><PencilIcon className="w-3 h-3 mr-1" />Edit</Button>
                                            </Link>
                                        )}
                                        {canDelete && (
                                            <Button variant="danger" onClick={handleDelete}>
                                                <TrashIcon className="w-4 h-4" />
                                            </Button>
                                        )}
                                    </div>
                                )}
                            </div>
                        </div>

                        {/* Description */}
                        {calendar.description && (
                            <div className="bg-white dark:bg-neutral-800 px-6 py-4">
                                <p className="text-sm text-neutral-700 dark:text-neutral-300 whitespace-pre-wrap">
                                    {calendar.description}
                                </p>
                            </div>
                        )}
                    </div>

                    {/* Events card */}
                    <div className="border border-neutral-200 dark:border-neutral-700">

                        {/* Card Header */}
                        <div className="bg-secondary dark:bg-neutral-800 border-b border-neutral-200 dark:border-neutral-700 px-6 py-4">
                            <div className="flex justify-between items-center">
                                <h2 className="text-lg font-semibold text-white">Events</h2>
                                {auth.user?.permissions?.includes('create-events') && (
                                    <Link href={`/events/create?calendar_id=${calendar.id}`}>
                                        <Button variant="success"><PlusIcon className="w-4 h-4 mr-1" />Create Event</Button>
                                    </Link>
                                )}
                            </div>
                        </div>

                        {/* Events list */}
                        <div className="bg-white dark:bg-neutral-800 divide-y divide-neutral-200 dark:divide-neutral-700">
                            {calendar.events && calendar.events.length > 0 ? (
                                calendar.events.map((event) => (
                                    <div
                                        key={event.id}
                                        className="flex flex-col sm:flex-row sm:items-center gap-3 sm:gap-6 px-6 py-4 bg-white dark:bg-neutral-800 hover:bg-neutral-50 dark:hover:bg-neutral-700/50 transition-colors"
                                    >
                                        {event.banner_path && (
                                            <div className="w-full sm:w-28 aspect-video shrink-0 overflow-hidden">
                                                <img
                                                    src={`/storage/${event.banner_path}`}
                                                    alt={event.title}
                                                    className="w-full h-full object-cover"
                                                />
                                            </div>
                                        )}
                                        <div className="flex-1 min-w-0">
                                            <div className="flex items-center gap-2 mb-1">
                                                <h3 className="text-base font-semibold text-neutral-900 dark:text-neutral-100">
                                                    {event.title}
                                                </h3>
                                                {event.recurrence_rule && (
                                                    <span className="inline-flex items-center px-2 py-0.5 text-xs font-medium bg-secondary text-white uppercase">
                                                        Recurring
                                                    </span>
                                                )}
                                            </div>
                                            <div className="text-sm text-neutral-500 dark:text-neutral-400">
                                                <DateTimeDisplay datetime={event.display_datetime || event.start_datetime} formatString="MMMM d, yyyy, HH:mm" />
                                            </div>
                                        </div>
                                        <div className="shrink-0">
                                            <Link
                                                href={`/events/${event.id}`}
                                                className="inline-block px-4 py-2 text-sm font-medium bg-secondary text-white hover:bg-secondary/90 transition-colors"
                                            >
                                                View Event
                                            </Link>
                                        </div>
                                    </div>
                                ))
                            ) : (
                                <div className="text-center py-12 flex flex-col items-center gap-3">
                                    <p className="text-neutral-500 dark:text-neutral-400">No events in this calendar yet.</p>
                                    {auth.user?.permissions?.includes('create-events') && (
                                        <Link href={`/events/create?calendar_id=${calendar.id}`}>
                                            <Button variant="secondary">Create First Event</Button>
                                        </Link>
                                    )}
                                </div>
                            )}
                        </div>
                    </div>

                </div>
            </Layout>
        </>
    );
}