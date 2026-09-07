import { Head, usePage } from '@inertiajs/react';
import Heading from '@/components/heading';
import { edit } from '@/routes/profile';

export default function Profile() {
    const { auth } = usePage().props;

    const fields = [
        ['CID', auth.user.cid],
        ['Full name', auth.user.name_full],
        ['Email', auth.user.email],
        ['Controller rating', auth.user.controller_rating],
        ['Division', auth.user.division ?? 'None'],
        ['Subdivision', auth.user.subdivision ?? 'None'],
    ];

    return (
        <>
            <Head title="Profile settings" />
            <div className="flex flex-col gap-6">
                <Heading
                    variant="small"
                    title="Profile information"
                    description="These details are supplied by your sign-in provider and refreshed each time you log in."
                />
                <dl className="flex flex-col gap-4">
                    {fields.map(([label, value]) => (
                        <div key={label} className="flex flex-col gap-1">
                            <dt className="text-muted-foreground text-sm">
                                {label}
                            </dt>
                            <dd className="text-sm font-medium break-words">
                                {value}
                            </dd>
                        </div>
                    ))}
                </dl>
            </div>
        </>
    );
}

Profile.layout = {
    breadcrumbs: [
        {
            title: 'Profile settings',
            href: edit(),
        },
    ],
};
