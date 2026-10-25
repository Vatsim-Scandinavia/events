import { Head, router } from '@inertiajs/react';
import { ShieldCheck } from 'lucide-react';
import type { ReactNode } from 'react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import AppLayout from '@/layouts/AppLayout';

type DevLoginUser = {
    id: number;
    name: string;
    cid: number | null;
    rating: string | null;
    roles: string[];
};

export default function DevLogin({ users }: { users: DevLoginUser[] }) {
    return (
        <>
            <Head title="Sign in" />

            <div className="mx-auto max-w-xl px-4 py-12 sm:px-6">
                <Card>
                    <CardHeader>
                        <CardTitle className="text-lg">Development sign-in</CardTitle>
                        <CardDescription>
                            VATSIM Connect isn't wired up yet, so on this local install you can sign in as any user. This page is off unless
                            AUTH_DEV_LOGIN is enabled.
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        {users.length === 0 ? (
                            <p className="text-sm text-muted-foreground">
                                There are no users yet. Run <code className="font-mono">php artisan db:seed</code> to add some.
                            </p>
                        ) : (
                            <ul className="divide-y">
                                {users.map((user) => (
                                    <li key={user.id} className="flex items-center justify-between gap-3 py-2.5">
                                        <div className="flex flex-col gap-0.5">
                                            <span className="flex items-center gap-2 text-sm font-medium">
                                                {user.name}
                                                {user.roles.map((role) => (
                                                    <Badge key={role} variant="secondary">
                                                        <ShieldCheck />
                                                        {role}
                                                    </Badge>
                                                ))}
                                            </span>
                                            <span className="text-xs text-muted-foreground">
                                                {[user.cid && `CID ${user.cid}`, user.rating].filter(Boolean).join(' · ')}
                                            </span>
                                        </div>
                                        <Button size="sm" variant="outline" onClick={() => router.post(`/login/${user.id}`)}>
                                            Sign in
                                        </Button>
                                    </li>
                                ))}
                            </ul>
                        )}
                    </CardContent>
                </Card>
            </div>
        </>
    );
}

DevLogin.layout = (page: ReactNode) => <AppLayout>{page}</AppLayout>;
