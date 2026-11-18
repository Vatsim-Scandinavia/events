import { Head, Link, router, usePage } from '@inertiajs/react';
import { Plane } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { signInUrl } from '@/lib/utils';

// Mirrors the props of App\Http\Controllers\SignInPageController@show.
type SignInProps = {
    provider: { label: string; url: string };
    // Where to land afterwards, a path on this site.
    returnTo: string | null;
    // The sandbox's test members to sign in as, only with APP_ENV=local.
    devCids: number[] | null;
};

export default function SignIn({ provider, returnTo, devCids }: SignInProps) {
    const { vacc } = usePage().props;
    const providerUrl = returnTo ? signInUrl(provider.url, returnTo) : provider.url;

    return (
        <>
            <Head title="Sign in" />

            <div className="flex min-h-svh flex-col items-center justify-center gap-6 bg-muted px-4 py-10">
                <div className="flex w-full max-w-sm flex-col gap-6">
                    <Link href="/" className="flex items-center gap-2 self-center font-semibold">
                        {vacc.logo ? (
                            // Decorative: the vACC's name is right next to it.
                            <img src={vacc.logo} alt="" className="size-8 shrink-0 object-contain" />
                        ) : (
                            <span className="flex size-8 shrink-0 items-center justify-center rounded-lg bg-primary text-primary-icon">
                                <Plane className="size-4" />
                            </span>
                        )}
                        {vacc.name}
                    </Link>

                    <Card>
                        <CardHeader className="text-center">
                            <CardTitle className="text-xl">Welcome</CardTitle>
                            <CardDescription>Sign in with your VATSIM account to book shifts and follow events.</CardDescription>
                        </CardHeader>
                        <CardContent>
                            <Button className="w-full" asChild>
                                {/* A full page visit: the sign-in provider is on another origin. */}
                                <a href={providerUrl}>{provider.label}</a>
                            </Button>
                        </CardContent>
                    </Card>

                    {devCids && <DevSignIn cids={devCids} returnTo={returnTo} />}
                </div>
            </div>
        </>
    );
}

function DevSignIn({ cids, returnTo }: { cids: number[]; returnTo: string | null }) {
    return (
        <Card>
            <CardHeader>
                <CardTitle>Development</CardTitle>
                <CardDescription>Sign in as one of the VATSIM Connect sandbox’s test members. Only offered with APP_ENV=local.</CardDescription>
            </CardHeader>
            <CardContent className="flex flex-wrap gap-2">
                {cids.map((cid) => (
                    <Button key={cid} size="sm" variant="outline" onClick={() => router.post(`/sign-in/dev/${cid}`, { return: returnTo })}>
                        {cid}
                    </Button>
                ))}
            </CardContent>
        </Card>
    );
}
