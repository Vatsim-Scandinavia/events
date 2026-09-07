import { Head, usePage } from '@inertiajs/react';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import { redirect } from '@/routes/oauth';

type Provider = {
    id: string;
    name: string;
    primary: boolean;
};

export default function Login({ providers }: { providers: Provider[] }) {
    const { errors } = usePage().props;

    return (
        <>
            <Head title="Log in" />
            <div className="flex flex-col gap-4">
                {errors.oauth && (
                    <Alert variant="destructive">
                        <AlertTitle>Sign-in unsuccessful</AlertTitle>
                        <AlertDescription>{errors.oauth}</AlertDescription>
                    </Alert>
                )}
                {providers.map((provider) => (
                    <Button
                        key={provider.id}
                        variant={provider.primary ? 'default' : 'outline'}
                        size="lg"
                        asChild
                    >
                        <a href={redirect.url(provider.id)}>
                            Continue with {provider.name}
                        </a>
                    </Button>
                ))}
                {providers.length === 0 && (
                    <Alert>
                        <AlertTitle>
                            Sign-in is currently unavailable
                        </AlertTitle>
                        <AlertDescription>
                            Please try again later or contact the site
                            administrator.
                        </AlertDescription>
                    </Alert>
                )}
                <p className="text-muted-foreground text-center text-sm">
                    Your account is created automatically the first time you
                    sign in.
                </p>
            </div>
        </>
    );
}

Login.layout = {
    title: 'Log in to Events',
    description:
        'Use your VATSIM account or your local organisation’s sign-in service.',
};
