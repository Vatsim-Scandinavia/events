import { Link, usePage, router, Head } from '@inertiajs/react';
import Layout from '../../Layouts/Layout';
import Button from '../../Components/Button';
import { format } from 'date-fns';
import DateTimeDisplay, { DateTimeRangeDisplay, TimeDisplay } from '../../Components/DateTimeDisplay';
import ReactMarkdown from 'react-markdown';

export default function Show({ event, instances, bannerUrl }) {
    const { auth } = usePage().props;

    const canEdit = auth.user?.permissions?.includes('edit-events') || 
                    event.created_by === auth.user?.id;

    const handleDelete = () => {
        if (confirm('Are you sure you want to delete this event?')) {
            router.delete(`/events/${event.id}`);
        }
    };

    console.log (event.display_datetime, event.start_datetime)
    console.log (event.display_end_datetime, event.end_datetime)

    return (
        <>
            <Head title={event.title} />
            <Layout auth={auth}>
            <div className="max-w-5xl mx-auto">
                <div className="mb-8">
                    <div className="flex justify-between items-center">
                        <div>
                            <h1 className="text-3xl font-bold text-secondary dark:text-primary">{event.title}</h1>
                            {event.recurrence_rule && (
                                <span 
                                    className="inline-flex items-center px-2 py-0.5 mt-2 text-xs font-medium text-white border-2"
                                    style={{ backgroundColor: 'var(--color-secondary)', borderColor: 'var(--color-secondary)' }}
                                >
                                    RECURRING EVENT
                                </span>
                            )}
                        </div>
                        {canEdit && (
                            <div className="flex space-x-2 ml-4">
                                {event.recurrence_rule && (
                                    <Link href={`/events/${event.id}/occurrences`}>
                                        <Button variant="outline">Manage Occurrences</Button>
                                    </Link>
                                )}
                                <Link href={`/events/${event.id}/edit`}>
                                    <Button variant="secondary">Edit</Button>
                                </Link>
                                <Button variant="danger" onClick={handleDelete}>
                                    Delete
                                </Button>
                            </div>
                        )}
                    </div>

                    {bannerUrl && (
                        <div className="relative w-full mt-6" style={{ aspectRatio: '16/9' }}>
                            <img
                                src={bannerUrl}
                                alt={event.title}
                                className="w-full h-full object-contain"
                            />
                        </div>
                    )}
                </div>

                <div className="bg-white dark:bg-dark-bg-secondary p-6 mb-6" style={{ boxShadow: 'var(--shadow-card)' }}>
                    <div className="grid grid-cols-2 gap-4 mb-6">
                        <div>
                            <h3 className="text-sm font-medium text-gray-500 dark:text-dark-text-secondary">Start Time</h3>
                            <div className="mt-1 text-lg">
                                <DateTimeDisplay datetime={event.display_datetime || event.start_datetime} />
                            </div>
                        </div>
                        <div>
                            <h3 className="text-sm font-medium text-gray-500 dark:text-dark-text-secondary">End Time</h3>
                            <div className="mt-1 text-lg">
                                <DateTimeDisplay datetime={event.display_end_datetime || event.end_datetime} />
                            </div>
                        </div>
                        <div>
                            <h3 className="text-sm font-medium text-gray-500 dark:text-dark-text-secondary">Calendar</h3>
                            <p className="mt-1">{event.calendar?.name}</p>
                        </div>
                        {event.featured_airports && event.featured_airports.length > 0 && (
                            <div>
                                <h3 className="text-sm font-medium text-gray-500 dark:text-dark-text-secondary">Featured Airports</h3>
                                <div className="mt-1 flex flex-wrap gap-2">
                                    {event.featured_airports.map((airport) => (
                                        <span
                                            key={airport}
                                            className="inline-flex items-center px-2.5 py-0.5 text-xs font-medium text-white font-mono"
                                            style={{ backgroundColor: 'var(--color-secondary)', boxShadow: 'var(--shadow-card)' }}
                                        >
                                            {airport}
                                        </span>
                                    ))}
                                </div>
                            </div>
                        )}
                    </div>

                    <div className="border-t pt-6" style={{ borderColor: 'rgba(0, 0, 0, 0.1)' }}>
                        <h3 className="text-lg font-medium text-gray-900 dark:text-dark-text mb-2">Description</h3>
                        <div className="prose prose-sm max-w-none text-gray-700 dark:text-dark-text">
                            <ReactMarkdown>{event.long_description}</ReactMarkdown>
                        </div>
                    </div>
                </div>

                {event.recurrence_rule && instances && instances.length > 1 && (
                    <div className="bg-white dark:bg-dark-bg-secondary p-6 mb-6" style={{ boxShadow: 'var(--shadow-card)' }}>
                        <h3 className="text-lg font-medium text-gray-900 dark:text-dark-text mb-4">Following Occurrences</h3>
                        <div className="space-y-2">
                            {instances.slice(1, 4).map((instance, index) => (
                                <div key={index} className={`flex justify-between py-2 border-b last:border-0 ${instance.cancelled ? 'opacity-50' : ''}`} style={{ borderColor: 'rgba(0, 0, 0, 0.1)' }}>
                                    <div className="flex items-center gap-2">
                                        <span className={`${instance.cancelled ? 'line-through' : ''} text-grey-900 dark:text-dark-text`}>
                                            {format(new Date(instance.start), 'PPP')}
                                        </span>
                                        {instance.cancelled && (
                                            <span className="inline-flex items-center px-2 py-0.5 text-xs font-medium bg-red-600 text-white">
                                                CANCELLED
                                            </span>
                                        )}
                                    </div>
                                    <span className="text-gray-600 dark:text-dark-text-secondary">
                                        <TimeDisplay datetime={instance.start} /> - <TimeDisplay datetime={instance.end} />
                                    </span>
                                </div>
                            ))}
                        </div>
                        {canEdit && (
                            <div className="mt-4 text-center">
                                <Link href={`/events/${event.id}/occurrences`}>
                                    <Button variant="outline-light" size="sm">View All Occurrences</Button>
                                </Link>
                            </div>
                        )}
                    </div>
                )}

                {/* Staffing Section - Only for recurring events */}
                {auth.user?.permissions?.includes('manage-staffings') && event.recurrence_rule && (
                    <div className="bg-white dark:bg-dark-bg-secondary" style={{ boxShadow: 'var(--shadow-card)' }}>
                        <div className="bg-grey-100 dark:bg-dark-bg-tertiary px-6 py-4 border-b-2 border-grey-200 dark:border-dark-border">
                            <div className="flex justify-between items-center">
                                <h3 className="text-xl font-semibold text-grey-900 dark:text-dark-text">Staffing</h3>
                                <Link href={`/events/${event.id}/staffings`}>
                                    <Button variant="secondary">Manage Staffing</Button>
                                </Link>
                            </div>
                        </div>
                        
                        {event.staffings && event.staffings.length > 0 ? (
                            <div className="p-6 space-y-6">
                                {event.staffings.map((staffing) => (
                                    <div key={staffing.id}>
                                        <h4 className="font-semibold text-grey-900 dark:text-dark-text mb-3">{staffing.name}</h4>
                                        <div className="space-y-2">
                                            {staffing.positions?.map((position) => (
                                                <div 
                                                    key={position.id} 
                                                    className="flex justify-between items-center py-3 px-4 bg-grey-50 dark:bg-dark-bg-tertiary"
                                                    style={{ border: '2px solid var(--color-grey-200)' }}
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
                                                            <span className="text-sm text-grey-700 dark:text-dark-text">
                                                                {position.position_name}
                                                            </span>
                                                            {(position.start_time || position.end_time) && (
                                                                <div className="text-xs text-grey-500 dark:text-dark-text-secondary mt-0.5">
                                                                    {position.start_time && (
                                                                        <TimeDisplay datetime={position.start_time} />
                                                                    )}
                                                                    {position.start_time && position.end_time && <span> - </span>}
                                                                    {position.end_time && (
                                                                        <TimeDisplay datetime={position.end_time} />
                                                                    )}
                                                                </div>
                                                            )}
                                                        </div>
                                                    </div>
                                                    {(position.booked_by || position.vatsim_cid) ? (
                                                        <span className="text-sm text-success font-medium">
                                                            {position.booked_by 
                                                                ? `Booked by ${position.booked_by.name}`
                                                                : `Booked by ${position.vatsim_cid}`
                                                            }
                                                        </span>
                                                    ) : (
                                                        <span className="text-sm text-grey-500 dark:text-dark-text-secondary font-medium">Available</span>
                                                    )}
                                                </div>
                                            ))}
                                        </div>
                                    </div>
                                ))}
                            </div>
                        ) : (
                            <div className="p-6 text-center">
                                <p className="text-grey-500 dark:text-dark-text-secondary">No staffing sections created yet.</p>
                            </div>
                        )}
                    </div>
                )}
            </div>
        </Layout>
        </>
    );
}
