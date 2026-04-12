import Navbar from '../Components/Navigation/Navbar';

export default function Layout({ children, className = 'flex flex-col gap-6' }) {
    return (
        <div className="min-h-screen flex flex-col transition-colors bg-neutral-100 dark:bg-neutral-900 text-neutral-900 dark:text-neutral-100">
            <Navbar />
            <main className="w-full flex-1 flex flex-col items-center mt-8">
                <div className={`w-full max-w-7xl mx-auto px-4 md:px-8 py-10 ${className}`}>
                    {children}
                </div>
            </main>
        </div>
    );
}