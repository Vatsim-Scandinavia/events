import { createContext, useCallback, useContext, useRef, useState, type ReactNode } from 'react';
import {
    AlertDialog,
    AlertDialogAction,
    AlertDialogCancel,
    AlertDialogContent,
    AlertDialogDescription,
    AlertDialogFooter,
    AlertDialogHeader,
    AlertDialogTitle,
} from '@/components/ui/alert-dialog';
import { buttonVariants } from '@/components/ui/button';

export type ConfirmOptions = {
    title: string;
    description: ReactNode;
    confirmLabel?: string;
    cancelLabel?: string;
    // Styles the confirm button as a destructive action.
    destructive?: boolean;
};

type Confirm = (options: ConfirmOptions) => Promise<boolean>;

const ConfirmContext = createContext<Confirm | null>(null);

/**
 * Provides an in-app replacement for window.confirm(): `await confirm({...})` resolves
 * to true when the person confirms and false when they cancel or dismiss the dialog.
 */
export function ConfirmProvider({ children }: { children: ReactNode }) {
    const [options, setOptions] = useState<ConfirmOptions | null>(null);
    const resolver = useRef<((value: boolean) => void) | null>(null);

    const confirm = useCallback<Confirm>(
        (next) =>
            new Promise((resolve) => {
                resolver.current?.(false);
                resolver.current = resolve;
                setOptions(next);
            }),
        [],
    );

    const settle = (value: boolean) => {
        resolver.current?.(value);
        resolver.current = null;
        setOptions(null);
    };

    return (
        <ConfirmContext.Provider value={confirm}>
            {children}
            <AlertDialog open={options !== null} onOpenChange={(open) => !open && settle(false)}>
                <AlertDialogContent>
                    <AlertDialogHeader>
                        <AlertDialogTitle>{options?.title}</AlertDialogTitle>
                        <AlertDialogDescription>{options?.description}</AlertDialogDescription>
                    </AlertDialogHeader>
                    <AlertDialogFooter>
                        <AlertDialogCancel onClick={() => settle(false)}>{options?.cancelLabel ?? 'Cancel'}</AlertDialogCancel>
                        <AlertDialogAction
                            className={options?.destructive ? buttonVariants({ variant: 'destructive' }) : undefined}
                            onClick={() => settle(true)}
                        >
                            {options?.confirmLabel ?? 'Continue'}
                        </AlertDialogAction>
                    </AlertDialogFooter>
                </AlertDialogContent>
            </AlertDialog>
        </ConfirmContext.Provider>
    );
}

export function useConfirm(): Confirm {
    const confirm = useContext(ConfirmContext);

    if (!confirm) {
        throw new Error('useConfirm() needs a <ConfirmProvider> above it.');
    }

    return confirm;
}
