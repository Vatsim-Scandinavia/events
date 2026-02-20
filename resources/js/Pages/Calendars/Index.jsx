import { Link, usePage, Head } from '@inertiajs/react';
import Layout from '../../Layouts/Layout';
import Button from '../../Components/Button';
import { PlusIcon } from '@heroicons/react/24/solid';

export default function Index({ calendars }) {
    const { auth } = usePage().props;

    return (
        <>
            <Head title="Calendars" />
            <Layout auth={auth}>
                <div className="w-full max-w-7xl mx-auto px-4 md:px-8 py-10 flex flex-col gap-6">

                    {/* Calendars List */}
                    <div className="border border-neutral-200 dark:border-neutral-700">
                        <div className="bg-secondary dark:bg-neutral-800 border-b border-neutral-200 dark:border-neutral-700 px-6 py-4">
                            <div className="flex justify-between items-center">
                                <h1 className="text-lg font-semibold text-white dark:text-neutral-100">Calendars</h1>
                                {auth.user?.permissions?.includes('create-calendars') && (
                                    <Link href="/calendars/create">
                                        <Button variant="success"><PlusIcon className="w-4 h-4 mr-1" />Create Calendar</Button>
                                    </Link>
                                )}
                            </div>
                        </div>

                        {calendars.data.length > 0 ? (
                            <div className="divide-y divide-neutral-200 dark:divide-neutral-700">
                                {calendars.data.map((calendar) => (
                                    <div
                                        key={calendar.id}
                                        className="flex flex-col sm:flex-row sm:items-center gap-3 sm:gap-6 px-6 py-4 bg-white dark:bg-neutral-800 hover:bg-neutral-50 dark:hover:bg-neutral-700/50 transition-colors"
                                    >
                                        <div className="flex-1 min-w-0">
                                            <div className="flex items-center gap-2 mb-1">
                                                <h3 className="text-base font-semibold text-neutral-900 dark:text-neutral-100">
                                                    {calendar.name}
                                                </h3>
                                                {!calendar.is_public && (
                                                    <span className="inline-flex items-center px-2 py-0.5 text-xs font-medium bg-warning text-white uppercase">
                                                        Private
                                                    </span>
                                                )}
                                            </div>
                                            {calendar.description && (
                                                <p className="text-sm text-neutral-500 dark:text-neutral-400 line-clamp-2">
                                                    {calendar.description}
                                                </p>
                                            )}
                                            <p className="text-sm text-neutral-500 dark:text-neutral-400 mt-0.5">
                                                Created by {calendar.creator?.name || 'Unknown'}
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
                            <div className="bg-white dark:bg-neutral-800 px-6 py-12 text-center">
                                <p className="text-sm text-neutral-500 dark:text-neutral-400">No calendars found.</p>
                            </div>
                        )}
                    </div>

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

                </div>
            </Layout>
        </>
    );
}