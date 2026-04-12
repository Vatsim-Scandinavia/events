import { Link, usePage, Head } from '@inertiajs/react';
import Layout from '../../Layouts/Layout';
import Button from '../../Components/Button';
import DateTimeDisplay from '../../Components/DateTimeDisplay';
import { ArrowPathIcon, PlusIcon } from '@heroicons/react/24/solid';

export default function Index({ events, filters }) {
    const { auth } = usePage().props;

    return (
        <>
            <Head title="All Events" />
            <Layout auth={auth}>
                {/* Events List */}
                <div className="border border-neutral-200 dark:border-neutral-700">
                    <div className="bg-secondary dark:bg-neutral-800 border-b border-neutral-200 dark:border-neutral-700 px-6 py-4">
                        <div className="flex justify-between items-center">
                            <h1 className="text-lg font-semibold text-white dark:text-neutral-100">All Events</h1>
                            {auth.user?.permissions?.includes('create-events') && (
                                <Link href="/events/create">
                                    <Button variant="success">
                                        <PlusIcon className="w-4 h-4 mr-1" />
                                        Create Event
                                    </Button>
                                </Link>
                            )}
                        </div>
                    </div>

                    {events.data.length > 0 ? (
                        <div className="divide-y divide-neutral-200 dark:divide-neutral-700">
                            {events.data.map((event) => (
                                <div
                                    key={event.id}
                                    className="flex flex-col sm:flex-row sm:items-center gap-3 sm:gap-6 px-6 py-4 bg-white dark:bg-neutral-800 hover:bg-neutral-50 dark:hover:bg-neutral-700/50 transition-colors"
                                >
                                    {event.banner_url && (
                                        <div className="w-full sm:w-28 aspect-video shrink-0 overflow-hidden">
                                            <img
                                                src={event.banner_url}
                                                alt={event.title}
                                                className="w-full h-full object-cover"
                                            />
                                        </div>
                                    )}
                                    <div className="flex-1 min-w-0">
                                        <div className="flex flex-col sm:flex-row sm:items-center gap-1 sm:gap-2 mb-1">
                                            <h3 className="text-base font-semibold text-neutral-900 dark:text-neutral-100">
                                                {event.title}
                                            </h3>
                                            {event.recurrence_rule && (
                                                <span className="inline-flex items-center px-2 py-0.5 text-xs font-medium bg-neutral-600 dark:bg-neutral-700 text-white uppercase w-fit">
                                                    <ArrowPathIcon className="w-3 h-3 mr-1" />
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
                            ))}
                        </div>
                    ) : (
                        <div className="bg-white dark:bg-neutral-800 px-6 py-12 text-center">
                            <p className="text-sm text-neutral-500 dark:text-neutral-400">No events found.</p>
                        </div>
                    )}
                </div>

                {/* Pagination */}
                {events.links?.length > 3 && (
                    <div className="flex justify-center gap-1">
                        {events.links.map((link, index) => (
                            <Link
                                key={index}
                                href={link.url || '#'}
                                className={`px-3 py-2 text-sm border transition-colors ${
                                    link.active
                                        ? 'bg-secondary text-white border-secondary'
                                        : 'bg-white dark:bg-neutral-800 text-neutral-700 dark:text-neutral-100 border-neutral-200 hover:border-secondary'
                                } ${!link.url ? 'opacity-40 pointer-events-none' : ''}`}
                                dangerouslySetInnerHTML={{ __html: link.label }}
                            />
                        ))}
                    </div>
                )}
            </Layout>
        </>
    );
}