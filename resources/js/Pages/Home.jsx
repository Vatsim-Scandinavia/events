import { Link, usePage, Head, router } from '@inertiajs/react';
import Layout from '../Layouts/Layout';
import { Calendar, momentLocalizer } from 'react-big-calendar';
import moment from 'moment';
import 'react-big-calendar/lib/css/react-big-calendar.css';
import { formatInTimeZone } from 'date-fns-tz';
import DateTimeDisplay from '../Components/DateTimeDisplay';

moment.updateLocale('en', {
    week: { dow: 1, doy: 4 }
});
const localizer = momentLocalizer(moment);

export default function Home({ upcomingEvents, calendarEvents }) {
    const { auth } = usePage().props;

    const events = calendarEvents
        .filter(event => !event.cancelled)
        .map(event => ({
            ...event,
            start: new Date(event.start),
            end: new Date(event.end),
        }));

    const eventStyleGetter = () => ({
        style: {
            backgroundColor: 'var(--color-secondary)',
            border: 'none',
            borderLeft: '3px solid var(--color-primary)',
            color: 'white',
            fontSize: '0.75rem',
            borderRadius: '0',
            padding: '0 4px',
            marginLeft: '3px',
            marginRight: '3px',
            marginBottom: '2px',
            width: 'calc(100% - 6px)',
        }
    });

    const handleSelectEvent = (event) => {
        router.visit(event.url || `/events/${event.id}`);
    };

    return (
        <>
            <Head title="Home" />
            <Layout auth={auth}>
                <div className="w-full max-w-7xl mx-auto px-4 md:px-8 py-10 flex flex-col gap-10">

                    {/* Upcoming Events */}
                    <section>
                        <div className="border border-neutral-200 dark:border-neutral-700">
                            <div className="bg-secondary dark:bg-neutral-800 border-b border-neutral-200 dark:border-neutral-700 px-6 py-4">
                                <h2 className="text-lg font-semibold text-white dark:text-neutral-100">Upcoming Events</h2>
                            </div>
                            <div className="divide-y divide-neutral-200 dark:divide-neutral-700">
                                {upcomingEvents.length === 0 && (
                                    <p className="px-6 py-10 text-center text-sm text-neutral-500 dark:text-neutral-400">
                                        No upcoming events.
                                    </p>
                                )}
                                {upcomingEvents.map((event) => {
                                    const displayDate = event.display_datetime || event.start_datetime;
                                    const zuluDate = formatInTimeZone(new Date(displayDate), 'UTC', 'MMMM d, yyyy');
                                    const zuluTime = formatInTimeZone(new Date(displayDate), 'UTC', 'HH:mm');

                                    return (
                                        <div
                                            key={`${event.id}-${displayDate}`}
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
                                                <h3 className="text-base font-semibold text-neutral-900 dark:text-neutral-100">
                                                    {event.title}
                                                </h3>
                                                <p className="text-sm text-neutral-500 dark:text-neutral-400 mt-0.5">
                                                    <DateTimeDisplay datetime={displayDate} formatString={`MMMM d, yyyy, HH:mm`} />
                                                </p>
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
                                    );
                                })}
                            </div>
                        </div>
                    </section>

                    {/* Event Calendar */}
                    <section className="hidden md:block">
                        <div className="border border-neutral-200 dark:border-neutral-700">
                            <div className="bg-secondary dark:bg-neutral-800 border-b border-neutral-200 dark:border-neutral-700 px-6 py-4">
                                <h2 className="text-lg font-semibold text-white dark:text-neutral-100">
                                    Event Calendar <span className="text-sm font-normal text-white/60 dark:text-neutral-400">(Zulu)</span>
                                </h2>
                            </div>
                            <div className="bg-white dark:bg-neutral-800 p-4 md:p-6 rbc-wrap">
                                <style>{`
                                    .dark .rbc-wrap .rbc-calendar,
                                    .dark .rbc-wrap .rbc-month-view,
                                    .dark .rbc-wrap .rbc-time-view,
                                    .dark .rbc-wrap .rbc-agenda-view {
                                        background: #262626;
                                        border-color: #404040;
                                        color: #f5f5f5;
                                    }
                                    .dark .rbc-wrap .rbc-header {
                                        background: #171717;
                                        border-color: #404040;
                                        color: #d4d4d4;
                                    }
                                    .dark .rbc-wrap .rbc-day-bg {
                                        background: #262626;
                                        border-color: #404040;
                                    }
                                    .dark .rbc-wrap .rbc-off-range-bg {
                                        background: #1a1a1a;
                                    }
                                    .dark .rbc-wrap .rbc-today {
                                        background: #1e3a4a;
                                    }
                                    .dark .rbc-wrap .rbc-month-row,
                                    .dark .rbc-wrap .rbc-row,
                                    .dark .rbc-wrap .rbc-row-bg,
                                    .dark .rbc-wrap .rbc-date-cell {
                                        border-color: #404040;
                                        color: #d4d4d4;
                                    }
                                    .dark .rbc-wrap .rbc-off-range {
                                        color: #737373;
                                    }
                                    .dark .rbc-wrap .rbc-toolbar button {
                                        color: #d4d4d4;
                                        border-color: #404040;
                                        background: #262626;
                                    }
                                    .dark .rbc-wrap .rbc-toolbar button:hover,
                                    .dark .rbc-wrap .rbc-toolbar button.rbc-active {
                                        background: #404040;
                                        color: #f5f5f5;
                                        border-color: #525252;
                                    }
                                    .dark .rbc-wrap .rbc-toolbar .rbc-toolbar-label {
                                        color: #f5f5f5;
                                    }
                                    .dark .rbc-wrap .rbc-show-more {
                                        color: #22d3ee;
                                    }
                                    .dark .rbc-wrap .rbc-time-slot,
                                    .dark .rbc-wrap .rbc-timeslot-group,
                                    .dark .rbc-wrap .rbc-time-gutter {
                                        border-color: #404040;
                                        color: #737373;
                                    }
                                    .dark .rbc-wrap .rbc-current-time-indicator {
                                        background: #22d3ee;
                                    }
                                `}</style>
                                <div className="h-170">
                                    <Calendar
                                        localizer={localizer}
                                        events={events}
                                        startAccessor="start"
                                        endAccessor="end"
                                        eventPropGetter={eventStyleGetter}
                                        onSelectEvent={handleSelectEvent}
                                        views={['month', 'week', 'day']}
                                        defaultView="month"
                                        culture="en-GB"
                                        titleAccessor={(e) => `${formatInTimeZone(e.start, 'UTC', 'HH:mm')}Z ${e.title}`}
                                        formats={{
                                            eventTimeRangeFormat: () => null,
                                        }}
                                    />
                                </div>
                            </div>
                        </div>
                    </section>

                    {/* CTA */}
                    <section className="bg-neutral-200 dark:bg-neutral-800 border border-neutral-200 dark:border-neutral-700 px-6 py-12 flex flex-col items-center gap-4 text-center">
                        <h3 className="text-2xl font-bold text-neutral-900 dark:text-neutral-100">
                            Looking for more?
                        </h3>
                        <p className="text-sm text-neutral-500 dark:text-neutral-400 max-w-sm">
                            Browse the full archive of past and future events from VATSIM Scandinavia.
                        </p>
                        <Link
                            href="/events"
                            className="mt-2 px-8 py-3 text-sm font-semibold bg-secondary text-white hover:bg-secondary/90 transition-colors"
                        >
                            Browse Full Event Archive
                        </Link>
                    </section>

                </div>
            </Layout>
        </>
    );
}