import { Head, usePage } from '@inertiajs/react';
import Heading from '@/components/heading';
import { edit } from '@/routes/profile';
import type { Auth } from '@/types';

export default function Profile() {
    const { auth } = usePage<{ auth: Auth }>().props;

    return (
        <>
            <Head title="Profile settings" />
            <div className="flex flex-col gap-6">
                <Heading
                    variant="small"
                    title="Profile"
                    description="Your identity is managed by your sign-in provider and refreshed when you log in."
                />
                <dl className="grid gap-4">
                    <div>
                        <dt className="text-muted-foreground text-sm">
                            VATSIM CID
                        </dt>
                        <dd>{auth.user.vatsim_cid ?? 'Not linked'}</dd>
                    </div>
                    <div>
                        <dt className="text-muted-foreground text-sm">Name</dt>
                        <dd>{auth.user.name}</dd>
                    </div>
                    <div>
                        <dt className="text-muted-foreground text-sm">Email</dt>
                        <dd>{auth.user.email}</dd>
                    </div>
                </dl>
            </div>
        </>
    );
}

Profile.layout = { breadcrumbs: [{ title: 'Profile settings', href: edit() }] };
