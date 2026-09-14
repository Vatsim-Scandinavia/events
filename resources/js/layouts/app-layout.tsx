import { SiteHeader } from '@/components/site-header';
import type { AppLayoutProps } from '@/types';

export default function AppLayout({ children }: AppLayoutProps) {
    return (
        <div className="min-h-screen">
            <a href="#main-content" className="site-skip-link">
                Skip to content
            </a>
            <div className="site-container">
                <SiteHeader />
                <main id="main-content" className="py-8">
                    {children}
                </main>
            </div>
        </div>
    );
}
