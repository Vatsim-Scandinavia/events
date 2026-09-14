import { Head } from '@inertiajs/react';
import { Button } from '@/components/ui/button';
import { redirect } from '@/routes/oauth';

type Props = { providerName: string; configured: boolean; error?: string };

export default function Login({ providerName, configured, error }: Props) {
    return (
        <>
            <Head title="Log in" />
            <div className="flex flex-col gap-6">
                <h1 className="text-xl font-semibold">Log in</h1>
                <p className="text-muted-foreground">
                    Continue with your {providerName} account.
                </p>
                {error && (
                    <p role="alert" className="text-destructive text-sm">
                        {error}
                    </p>
                )}
                {configured ? (
                    <Button asChild>
                        <a href={redirect.url()}>
                            Continue with {providerName}
                        </a>
                    </Button>
                ) : (
                    <p role="status" className="text-muted-foreground text-sm">
                        Sign-in is not configured yet. Please contact the site
                        administrator.
                    </p>
                )}
            </div>
        </>
    );
}
