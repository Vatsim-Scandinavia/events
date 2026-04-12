import { Link, usePage, router, Head } from '@inertiajs/react';
import Layout from '../../Layouts/Layout';
import Button from '../../Components/Button';
import Card from '../../Components/Card';
import DateTimeDisplay from '../../Components/DateTimeDisplay';
import { TrashIcon, PencilIcon, PlusIcon } from '@heroicons/react/24/solid';

export default function Show({ calendar }) {
    const { auth } = usePage().props;
    const cal = calendar.data ?? calendar;

    const canManage = auth.user?.permissions?.includes('manage calendars');

    const handleDelete = () => {
        if (confirm('Are you sure you want to delete this calendar? This will also delete all events in this calendar.')) {
            router.delete(`/calendars/${cal.id}`);
        }
    };

    return (
        <>
            <Head title={cal.title} />
            <Layout auth={auth}>
                <div className="w-full max-w-7xl mx-auto px-4 md:px-8 py-10 flex flex-col gap-6">

                    <Card 
                        title={cal.title}
                        subtitle={`Created by ${cal.creator?.full_name || 'N/A'}`}
                        label={cal.visibility}
                        actions={
                            canManage && (
                                <div className="flex gap-3">
                                    <Link href={`/calendars/${cal.id}/edit`}>
                                        <Button variant="secondary"><PencilIcon className="w-3 h-3 mr-1" />Edit</Button>
                                    </Link>
                                    <Button variant="danger" onClick={handleDelete}>
                                        <TrashIcon className="w-4 h-4" />
                                    </Button>
                                </div>
                            )
                        }
                    >
                        {cal.description && (
                            <div className="bg-white dark:bg-neutral-800 px-6 py-4">
                                <p className="text-sm font-semibold text-neutral-700 dark:text-neutral-300">Description</p>
                                <p className="text-sm text-neutral-700 dark:text-neutral-300 whitespace-pre-wrap">
                                    {cal.description || 'No description provided.'}
                                </p>
                            </div>
                        )}
                    </Card>

                    {/* Events card */}
                    <Card
                        title="Events"
                        subtitle={`${cal.events ? cal.events.length : 0} event(s)`}
                        actions={
                            canManage && (
                                <Link href={`/events/create?calendar_id=${cal.id}`}>
                                    <Button variant="success"><PlusIcon className="w-4 h-4 mr-1" />Create Event</Button>
                                </Link>
                            )
                        }
                    >
                        <div className="bg-white dark:bg-neutral-800 divide-y divide-neutral-200 dark:divide-neutral-700">
                            {cal.events && cal.events.length > 0 ? (
                                cal.events.map((event) => (
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
                                </div>
                            )}
                        </div>
                    </Card>
                </div>
            </Layout>
        </>
    );
}