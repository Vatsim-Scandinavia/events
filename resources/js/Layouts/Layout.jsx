<<<<<<< feat/ui-rework
import Navbar from '../Components/Navigation/Navbar';

export default function Layout({ children }) {
    return (
        <div className="min-h-screen flex flex-col transition-colors bg-neutral-100 dark:bg-neutral-900 text-neutral-900 dark:text-neutral-100">
            <Navbar />
            <main className="w-full flex-1 flex flex-col items-center mt-8">
                {children}
            </main>
=======
import { Link } from '@inertiajs/react';
import DarkModeToggle from '../Components/DarkModeToggle';
import { useState } from 'react';

export default function Layout({ children, auth }) {
    const [isMobileMenuOpen, setIsMobileMenuOpen] = useState(false);

    return (
        <div className="min-h-screen flex flex-col transition-colors bg-background">
            <div className="layout-root min-h-screen flex flex-col">
                <nav className="layout-nav bg-secondary">
                    <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                        <div className="flex justify-between h-16">
                            {/* Logo and Desktop Nav */}
                            <div className="flex">
                                <div className="flex-shrink-0 flex items-center">
                                    <Link href="/" className="flex items-center gap-3">
                                        <img 
                                            src="/images/logo.svg" 
                                            alt="VATSIM Scandinavia Logo" 
                                            className="h-10 w-auto"
                                            onError={(e) => {
                                                e.target.style.display = 'none';
                                            }}
                                        />
                                        <span className="text-xl font-bold text-white">
                                            VATSIM Scandinavia
                                        </span>
                                    </Link>
                                </div>
                                
                                {/* Desktop Navigation Links */}
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
                            
                            {/* Desktop Auth Section */}
                            <div className="hidden sm:flex items-center gap-2">
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

                            {/* Mobile Header Actions (Toggle + Menu Button) */}
                            <div className="sm:hidden flex items-center gap-2">
                                <DarkModeToggle />
                                <button
                                    onClick={() => setIsMobileMenuOpen(!isMobileMenuOpen)}
                                    className="inline-flex items-center justify-center p-2 rounded-md text-snow hover:bg-tertiary focus:outline-none"
                                    aria-expanded={isMobileMenuOpen}
                                >
                                    <span className="sr-only">Open main menu</span>
                                    {isMobileMenuOpen ? (
                                        <svg className="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M6 18L18 6M6 6l12 12" />
                                        </svg>
                                    ) : (
                                        <svg className="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M4 6h16M4 12h16M4 18h16" />
                                        </svg>
                                    )}
                                </button>
                            </div>
                        </div>

                        {/* Mobile Navigation Menu Dropdown */}
                        {isMobileMenuOpen && (
                            <div className="sm:hidden pb-4">
                                <Link
                                    href="/"
                                    className="text-snow hover:bg-tertiary block px-4 py-2 text-base font-medium transition-colors"
                                >
                                    Home
                                </Link>
                                {(auth?.user?.roles?.some(r => ['admin', 'moderator'].includes(r)) || 
                                  auth?.user?.permissions?.some(p => ['manage-calendars', 'manage-events', 'create-calendars', 'edit-calendars'].includes(p))) && (
                                    <Link
                                        href="/calendars"
                                        className="text-snow hover:bg-tertiary block px-4 py-2 text-base font-medium transition-colors"
                                    >
                                        Calendars
                                    </Link>
                                )}
                                <Link
                                    href="/events"
                                    className="text-snow hover:bg-tertiary block px-4 py-2 text-base font-medium transition-colors"
                                >
                                    Events
                                </Link>
                                <hr className="my-2 border-tertiary" />
                                {auth?.user ? (
                                    <div className="px-4 py-2">
                                        <p className="text-sm text-snow font-medium mb-2">{auth.user.name}</p>
                                        <Link
                                            href="/logout"
                                            method="post"
                                            as="button"
                                            className="block text-sm text-snow hover:text-white w-full text-left"
                                        >
                                            Logout
                                        </Link>
                                    </div>
                                ) : (
                                    <div className="px-4 py-2 space-y-2">
                                        <a
                                            href="/auth/vatsim"
                                            className="block text-sm text-snow hover:text-white"
                                        >
                                            Login with VATSIM
                                        </a>
                                        {import.meta.env.DEV && (
                                            <Link
                                                href="/dev/login"
                                                className="block text-sm text-warning hover:text-warning-300"
                                            >
                                                Dev Login
                                            </Link>
                                        )}
                                    </div>
                                )}
                            </div>
                        )}
                    </div>
                </nav>

                <main className="flex-grow py-8">
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
>>>>>>> v2
        </div>
    );
}