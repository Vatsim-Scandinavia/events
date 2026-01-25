import { Link, usePage, Head, router } from '@inertiajs/react';
import Layout from '../Layouts/Layout';
import { Calendar, momentLocalizer } from 'react-big-calendar';
import moment from 'moment';
import 'react-big-calendar/lib/css/react-big-calendar.css';
import { formatInTimeZone } from 'date-fns-tz';

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
        className: 'bg-primary dark:bg-primary border-none text-white text-xs rounded-sm px-1 shadow-sm',
        style: {
            backgroundColor: 'var(--color-secondary)',
        }
    });

    const handleSelectEvent = (event) => {
        router.visit(event.url || `/events/${event.id}`);
    };

    return (
        <>
            <Head title="Home" />
            <Layout auth={auth}>

                <div className="mb-12">
                    <div className="bg-white dark:bg-dark-bg-secondary shadow-card overflow-hidden">
                        <div className="bg-secondary dark:bg-dark-bg-tertiary px-6 py-4">
                            <h2 className="text-2xl font-semibold text-white">Upcoming Events</h2>
                        </div>
                        
                        <div className="divide-y divide-grey-100 dark:divide-dark-border">
                        {upcomingEvents.map((event, index) => {
                            const displayDate = event.display_datetime || event.start_datetime;
                            const zuluDate = formatInTimeZone(new Date(displayDate), 'UTC', 'MMMM d, yyyy');
                            const zuluTime = formatInTimeZone(new Date(displayDate), 'UTC', 'HH:mm');
                            const isLast = index === upcomingEvents.length - 1;
                            
                            return (
                                <div 
                                    key={`${event.id}-${displayDate}`} 
                                    className="flex items-center p-6 hover:bg-grey-50 dark:hover:bg-dark-bg-tertiary transition-colors gap-6"
                                    style={{ 
                                        borderBottom: isLast ? 'none' : '1px solid rgba(0, 0, 0, 0.1)'
                                    }}
                                >
                                    {event.banner_path && (
                                        <div className="w-32 flex-shrink-0" style={{ aspectRatio: '16/9' }}>
                                            <img
                                                src={`/storage/${event.banner_path}`}
                                                alt={event.title}
                                                className="w-full h-full object-cover"
                                            />
                                        </div>
                                    )}
                                    <div className="flex-1 min-w-0">
                                        <h3 className="text-lg font-bold text-secondary dark:text-primary mb-1">
                                            {event.title}
                                        </h3>
                                        <div className="text-sm text-grey-600 dark:text-dark-text-secondary">
                                            {zuluDate}, {zuluTime}Z
                                        </div>
                                    </div>
                                    <div className="flex-shrink-0">
                                        <Link 
                                            href={`/events/${event.id}`}
                                            className="inline-block px-4 py-2 bg-primary text-white hover:bg-primary-600 font-medium text-sm transition-colors"
                                        >
                                            View Event
                                        </Link>
                                    </div>
                                </div>
                            );
                        })}
                        </div>
                    </div>
                </div>

                <div className="mb-12">
                    <div className="bg-white dark:bg-dark-bg-secondary shadow-card">
                        <div className="bg-secondary dark:bg-dark-bg-tertiary px-6 py-4">
                            <h2 className="text-2xl font-semibold text-white">Event Calendar (Zulu)</h2>
                        </div>
                        <div className="p-6">
                            <div className="h-[700px] dark:calendar-dark">
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
                </div>

                <div className="text-center py-12 bg-grey-50 dark:bg-dark-bg-tertiary">
                    <h3 className="text-2xl font-bold text-secondary dark:text-primary mb-6">Looking for more?</h3>
                    <Link href="/events" className="inline-block px-8 py-3 bg-primary text-white border-2 border-primary hover:bg-transparent hover:text-primary transition-all font-semibold shadow-sm">
                        Browse Full Event Archive
                    </Link>
                </div>
            </Layout>
        </>
    );
}