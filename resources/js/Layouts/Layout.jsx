import Navbar from '../Components/Navigation/Navbar';

export default function Layout({ children }) {
    return (
        <div className="min-h-screen flex flex-col transition-colors bg-neutral-100 dark:bg-neutral-900 text-neutral-900 dark:text-neutral-100">
            <Navbar />
            <main className="w-full flex-1 flex flex-col items-center mt-8">
                {children}
            </main>
        </div>
    );
}