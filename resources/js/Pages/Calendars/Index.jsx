import { Link, usePage, Head } from '@inertiajs/react';
import Layout from '../../Layouts/Layout';
import Button from '../../Components/Button';
import Card from '../../Components/Card';
import { PlusIcon } from '@heroicons/react/24/solid';

export default function Index({ calendars }) {
    const { auth } = usePage().props;

    const canManage = auth.user?.permissions?.includes('manage calendars');

    return (
        <>
            <Head title="Calendars" />
            <Layout auth={auth}>
                <Card title="Calendars" actions={
                    canManage && (
                        <Link href="/calendars/create">
                            <Button variant="success"><PlusIcon className="w-4 h-4 mr-1" />Create Calendar</Button>
                        </Link>
                    )
                }>
                    {calendars.data.length > 0 ? (
                        <div className="divide-y divide-neutral-200 dark:divide-neutral-700">
                            {calendars.data.map((calendar) => (
                                <div key={calendar.id} className="flex flex-col sm:flex-row sm:items-center gap-3 sm:gap-6 px-6 py-4 bg-white dark:bg-neutral-800 hover:bg-neutral-50 dark:hover:bg-neutral-700/50 transition-colors">
                                    <div className="flex-1 min-w-0">
                                        <div className="flex items-center gap-2 mb-1">
                                            <h3 className="text-base font-semibold text-neutral-900 dark:text-neutral-100">
                                                {calendar.title}
                                            </h3>
                                            {calendar.visibility && (
                                                <span className={`px-2 py-0.5 text-[10px] font-bold tracking-wider text-white text-xs uppercase ${calendar.visibility === 'private' ? 'bg-danger' : 'bg-success'}`}>
                                                    {calendar.visibility}
                                                </span>
                                            )}
                                        </div>
                                        {calendar.description && (
                                            <p className="text-sm text-neutral-500 dark:text-neutral-400 line-clamp-2">
                                                {calendar.description}
                                            </p>
                                        )}
                                        <p className="text-sm text-neutral-500 dark:text-neutral-400 mt-0.5">
                                            Created by {calendar.creator?.full_name || 'Unknown'}
                                        </p>
                                    </div>
                                    <div className="shrink-0">
                                        <Link
                                            href={`/calendars/${calendar.id}`}
                                            className="inline-block px-4 py-2 text-sm font-medium bg-secondary text-white hover:bg-secondary/90 transition-colors"
                                        >
                                            View Calendar
                                        </Link>
                                    </div>
                                </div>
                            ))}
                        </div>
                    ) : (
                        <p>No calendars found.</p>
                    )}
                </Card>

                {/* Pagination */}
                {calendars.links?.length > 3 && (
                    <div className="flex justify-center gap-1">
                        {calendars.links.map((link, index) => (
                            <Link
                                key={index}
                                href={link.url || '#'}
                                className={`px-3 py-2 text-sm border transition-colors ${
                                    link.active
                                        ? 'bg-secondary text-white border-secondary dark:bg-primary dark:text-neutral-900 dark:border-primary'
                                        : 'bg-white dark:bg-neutral-800 text-neutral-700 dark:text-neutral-300 border-neutral-200 dark:border-neutral-700 hover:border-secondary dark:hover:border-primary'
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