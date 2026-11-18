import { Head, Link, router, usePage } from '@inertiajs/react';
import { Plane } from 'lucide-react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { signInUrl } from '@/lib/utils';

// Mirrors the props of App\Http\Controllers\SignInPageController@show.
type DevLink = {
    cid: number;
    // Null until the Account exists; signing in creates it.
    name: string | null;
    roles: string[];
};

type SignInProps = {
    provider: { label: string; url: string };
    // Where to land afterwards, a path on this site.
    returnTo: string | null;
    // Only on a developer's own machine.
    devLinks: DevLink[] | null;
};

export default function SignIn({ provider, returnTo, devLinks }: SignInProps) {
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

                    {devLinks && <DevLinks links={devLinks} returnTo={returnTo} />}
                </div>
            </div>
        </>
    );
}

function DevLinks({ links, returnTo }: { links: DevLink[]; returnTo: string | null }) {
    return (
        <Card>
            <CardHeader>
                <CardTitle>Development</CardTitle>
                <CardDescription>Sign in as one of the VATSIM Connect sandbox’s test members. Only offered on your own machine.</CardDescription>
            </CardHeader>
            <CardContent>
                <ul className="flex flex-col divide-y">
                    {links.map((link) => (
                        <li key={link.cid} className="flex items-center justify-between gap-3 py-2.5 first:pt-0 last:pb-0">
                            <span className="flex min-w-0 flex-col gap-1">
                                <span className="text-sm font-medium">
                                    {link.cid}
                                    {link.name && <span className="font-normal text-muted-foreground"> · {link.name}</span>}
                                </span>
                                <span className="flex flex-wrap gap-1">
                                    {link.name === null ? (
                                        <span className="text-xs text-muted-foreground">No Account yet</span>
                                    ) : (
                                        link.roles.map((role) => (
                                            <Badge key={role} variant="secondary">
                                                {role}
                                            </Badge>
                                        ))
                                    )}
                                </span>
                            </span>
                            <Button
                                size="sm"
                                variant="outline"
                                onClick={() => router.post(`/sign-in/dev/${link.cid}`, { return: returnTo })}
                                aria-label={`Sign in as ${link.cid}`}
                            >
                                Sign in
                            </Button>
                        </li>
                    ))}
                </ul>
            </CardContent>
        </Card>
    );
}
