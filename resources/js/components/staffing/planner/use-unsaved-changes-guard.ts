import { router } from '@inertiajs/react';
import { useEffect } from 'react';
import { useConfirm } from '@/components/confirm-dialog';

// Asks before leaving the page (or switching date or settings) with unsaved changes. Closing
// or reloading the tab can only use the browser's own prompt.
export function useUnsavedChangesGuard(active: boolean) {
    const confirm = useConfirm();

    useEffect(() => {
        if (!active) {
            return;
        }

        let leaving = false;
        const beforeUnload = (event: BeforeUnloadEvent) => event.preventDefault();
        const removeRouterGuard = router.on('before', (event) => {
            const { visit } = event.detail;

            if (leaving || visit.url.pathname.endsWith('/staffing/plan')) {
                return;
            }

            confirm({
                title: 'Leave without saving?',
                description: 'You have unsaved staffing changes. They’ll be lost if you leave now.',
                confirmLabel: 'Leave without saving',
                cancelLabel: 'Keep editing',
                destructive: true,
            }).then((leave) => {
                if (leave) {
                    leaving = true;
                    router.visit(visit.url, {
                        method: visit.method,
                        data: visit.data,
                        preserveScroll: visit.preserveScroll,
                        preserveState: visit.preserveState,
                    });
                }
            });

            return false;
        });

        window.addEventListener('beforeunload', beforeUnload);

        return () => {
            window.removeEventListener('beforeunload', beforeUnload);
            removeRouterGuard();
        };
    }, [active, confirm]);
}
