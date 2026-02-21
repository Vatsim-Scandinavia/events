import { useState, useEffect } from "react";
import { Link, useForm, usePage } from "@inertiajs/react";

const navLinks = [
    { label: "Home", href: "/" },
    { label: "Calendars", href: "/calendars" },
    { label: "Events", href: "/events" },
];

function SunIcon() {
    return (
        <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
            <circle cx="12" cy="12" r="4" />
            <path d="M12 2v2M12 20v2M4.93 4.93l1.41 1.41M17.66 17.66l1.41 1.41M2 12h2M20 12h2M4.93 19.07l1.41-1.41M17.66 6.34l1.41-1.41" />
        </svg>
    );
}

function MoonIcon() {
    return (
        <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
            <path d="M12 3a6 6 0 0 0 9 9 9 9 0 1 1-9-9Z" />
        </svg>
    );
}

function DarkModeToggle() {
    const [isDark, setIsDark] = useState(
        () => document.documentElement.classList.contains("dark")
    );

    useEffect(() => {
        if (isDark) {
            document.documentElement.classList.add("dark");
            localStorage.setItem("theme", "dark");
        } else {
            document.documentElement.classList.remove("dark");
            localStorage.setItem("theme", "light");
        }
    }, [isDark]);

    return (
        <button
            onClick={() => setIsDark((prev) => !prev)}
            aria-label={isDark ? "Switch to light mode" : "Switch to dark mode"}
            className="flex items-center justify-center w-9 h-9 rounded transition-colors text-neutral-300 hover:text-white hover:bg-neutral-700"
        >
            <span className={`transition-transform duration-300 ${isDark ? "rotate-0 scale-100" : "rotate-90 scale-75 opacity-0 absolute"}`}>
                <SunIcon />
            </span>
            <span className={`transition-transform duration-300 ${isDark ? "rotate-90 scale-75 opacity-0 absolute" : "rotate-0 scale-100"}`}>
                <MoonIcon />
            </span>
        </button>
    );
}

export default function Navbar() {
    const [menuOpen, setMenuOpen] = useState(false);
    const { auth } = usePage().props;
    const { post } = useForm();

    const logout = () => post("/logout");

    // Filter nav links based on permissions
    const visibleNavLinks = navLinks.filter(link => {
        if (link.href === '/calendars') {
            return auth?.user?.permissions?.includes('view-calendars') || auth?.user?.roles?.includes('admin');
        }
        return true;
    });

    return (
        <header className="bg-secondary dark:bg-neutral-800 text-neutral-100 shadow-lg">
            <div className="container flex h-20 max-w-screen-2xl items-center justify-between px-4 md:px-8 mx-auto">

                {/* Logo + Nav */}
                <div className="flex items-center gap-8">
                    <a href="/" className="shrink-0">
                        <img
                            src="/images/logos/negative.svg"
                            alt="VATSIM Scandinavia Logo"
                            className="h-12 w-autok"
                        />
                    </a>

                    {/* Desktop Nav */}
                    <nav className="hidden lg:flex items-center gap-1">
                        {visibleNavLinks.map(({ label, href }) => (
                            <Link
                                key={href}
                                href={href}
                                className="px-4 py-2 text-sm font-medium rounded transition-colors hover:text-white hover:bg-neutral-700"
                            >
                                {label}
                            </Link>
                        ))}
                    </nav>
                </div>

                {/* Desktop right side: auth + toggle */}
                <div className="hidden lg:flex items-center gap-3">
                    {auth?.user ? (
                        <>
                            <span className="text-sm font-medium text-neutral-100/80">
                                {auth.user.name}
                            </span>
                            <button
                                onClick={logout}
                                className="px-4 py-2 text-sm font-medium rounded border border-neutral-100/40 hover:border-neutral-100 transition-colors"
                            >
                                Logout
                            </button>
                        </>
                    ) : (
                        <>
                            <Link
                                href="/auth/vatsim"
                                className="px-4 py-2 text-sm font-medium rounded border border-neutral-100/40 hover:border-neutral-100 transition-colors"
                            >
                                Login with VATSIM
                            </Link>
                            {import.meta.env.DEV && (
                                <Link
                                    href="/dev/login"
                                    className="px-4 py-2.5 text-sm font-medium rounded border border-warning/40 hover:border-warning text-center transition-colors"
                                >
                                    DEV Login (Magic Link)
                                </Link>
                            )}
                        </>
                    )}
                    <div className="w-px h-5 bg-neutral-600" />
                    <DarkModeToggle />
                </div>

                {/* Mobile right side: toggle + hamburger */}
                <div className="lg:hidden flex items-center gap-2">
                    <DarkModeToggle />
                    <button
                        className="flex flex-col justify-center items-center w-10 h-10 gap-1.5 rounded hover:bg-neutral-700/60 transition-colors"
                        onClick={() => setMenuOpen((prev) => !prev)}
                        aria-label="Toggle menu"
                        aria-expanded={menuOpen}
                    >
                        <span className={`block h-0.5 w-6 bg-neutral-100 rounded transition-transform duration-300 origin-center ${menuOpen ? "rotate-45 translate-y-2" : ""}`} />
                        <span className={`block h-0.5 w-6 bg-neutral-100 rounded transition-opacity duration-300 ${menuOpen ? "opacity-0" : ""}`} />
                        <span className={`block h-0.5 w-6 bg-neutral-100 rounded transition-transform duration-300 origin-center ${menuOpen ? "-rotate-45 -translate-y-2" : ""}`} />
                    </button>
                </div>
            </div>

            {/* Mobile Menu */}
            <div
                className={`lg:hidden overflow-hidden transition-all duration-300 ease-in-out bg-neutral-900/50 ${
                    menuOpen ? "max-h-96 opacity-100" : "max-h-0 opacity-0"
                }`}
            >
                <nav className="flex flex-col px-4 pb-4 gap-1">
                    {visibleNavLinks.map(({ label, href }) => (
                        <Link
                            key={href}
                            href={href}
                            onClick={() => setMenuOpen(false)}
                            className="px-4 py-2.5 text-sm font-medium rounded transition-colors hover:text-white hover:bg-neutral-700"
                        >
                            {label}
                        </Link>
                    ))}

                    {/* Mobile Auth */}
                    <div className="mt-3 pt-3 border-t border-neutral-100/20 flex flex-col gap-2">
                        {auth?.user ? (
                            <>
                                <span className="px-4 py-2 text-sm font-medium text-neutral-100/60">
                                    Signed in as {auth.user.name}
                                </span>
                                <button
                                    onClick={() => { setMenuOpen(false); logout(); }}
                                    className="px-4 py-2.5 text-sm font-medium rounded border border-neutral-100/40 hover:border-neutral-100 text-center transition-colors"
                                >
                                    Logout
                                </button>
                            </>
                        ) : (
                            <>
                                <Link
                                    href="/auth/vatsim"
                                    onClick={() => setMenuOpen(false)}
                                    className="px-4 py-2.5 text-sm font-medium rounded border border-neutral-100/40 hover:border-neutral-100 text-center transition-colors"
                                >
                                    Login with VATSIM
                                </Link>
                                {import.meta.env.DEV && (
                                    <Link
                                        href="/dev/login"
                                        onClick={() => setMenuOpen(false)}
                                        className="px-4 py-2.5 text-sm font-medium rounded border border-warning/40 hover:border-warning text-center transition-colors"
                                    >
                                        DEV Login (Magic Link)
                                    </Link>
                                )}
                            </>
                        )}
                    </div>
                </nav>
            </div>
        </header>
    );
}