import { Link } from '@inertiajs/react';
import DarkModeToggle from '../Components/DarkModeToggle';

export default function Layout({ children, auth }) {
    return (
        <div className="min-h-screen flex flex-col transition-colors bg-background">
            <div className="layout-root min-h-screen flex flex-col">
                <nav className="layout-nav bg-secondary">
                    <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                        <div className="flex justify-between h-16">
                            <div className="flex">
                                <div className="shrink-0 flex items-center">
                                    <Link href="/" className="flex items-center gap-3">
                                        {/* Logo - place your SVG in public/images/logo.svg */}
                                        <img 
                                            src="/images/logo.svg" 
                                            alt="VATSIM Scandinavia Logo" 
                                            className="h-10 w-auto"
                                            onError={(e) => {
                                                // Hide logo if it doesn't exist
                                                e.target.style.display = 'none';
                                            }}
                                        />
                                        <span className="text-xl font-bold text-white">
                                            VATSIM Scandinavia
                                        </span>
                                    </Link>
                                </div>
                                <div className="hidden sm:ml-8 sm:flex sm:space-x-1">
                                    <Link
                                        href="/"
                                        className="text-snow hover:bg-tertiary hover:text-white inline-flex items-center px-4 py-2 text-sm font-medium transition-colors"
                                    >
                                        Home
                                    </Link>
                                    {(auth?.user?.roles?.some(r => ['admin', 'moderator'].includes(r)) || 
                                      auth?.user?.permissions?.some(p => ['manage-calendars', 'manage-events', 'create-calendars', 'edit-calendars'].includes(p))) && (
                                        <Link
                                            href="/calendars"
                                            className="text-snow hover:bg-tertiary hover:text-white inline-flex items-center px-4 py-2 text-sm font-medium transition-colors"
                                        >
                                            Calendars
                                        </Link>
                                    )}
                                    <Link
                                        href="/events"
                                        className="text-snow hover:bg-tertiary hover:text-white inline-flex items-center px-4 py-2 text-sm font-medium transition-colors"
                                    >
                                        Events
                                    </Link>
                                </div>
                            </div>
                            <div className="flex items-center gap-2">
                                <DarkModeToggle />
                                {auth?.user ? (
                                    <div className="flex items-center space-x-4">
                                        <span className="text-sm text-snow font-medium">
                                            {auth.user.name}
                                        </span>
                                        <Link
                                            href="/logout"
                                            method="post"
                                            as="button"
                                            className="text-sm text-snow hover:text-white transition-colors font-medium"
                                        >
                                            Logout
                                        </Link>
                                    </div>
                                ) : (
                                    <div className="flex items-center space-x-4">
                                        <a
                                            href="/auth/vatsim"
                                            className="text-sm text-snow hover:text-white transition-colors font-medium"
                                        >
                                            Login with VATSIM
                                        </a>
                                        {import.meta.env.DEV && (
                                            <Link
                                                href="/dev/login"
                                                className="text-sm text-warning hover:text-warning-300 font-medium transition-colors"
                                            >
                                                Dev Login
                                            </Link>
                                        )}
                                    </div>
                                )}
                            </div>
                        </div>
                    </div>
                </nav>

                <main className="grow py-8">
                    <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                        {children}
                    </div>
                </main>
                
                <footer className="layout-footer bg-white border-t-2 border-grey-200 mt-12">
                    <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6">
                        <div className="text-center text-sm text-grey-600 dark:text-dark-text-secondary">
                            <p className="font-medium">VATSIM Scandinavia - Event Management</p>
                            <p className="mt-1">Part of the VATSIM Network</p>
                        </div>
                    </div>
                </footer>
            </div>
        </div>
    );
}
