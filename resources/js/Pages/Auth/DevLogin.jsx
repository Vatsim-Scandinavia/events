import { useState } from 'react';
import { useForm, usePage } from '@inertiajs/react';
import Layout from '../../Layouts/Layout';
import Button from '../../Components/Button';
import Select from '../../Components/Select';

export default function DevLogin({ users }) {
    const { flash } = usePage().props;
    const [copiedLink, setCopiedLink] = useState(false);

    const { data, setData, post, processing } = useForm({
        email: users[0]?.email || '',
    });

    const handleSubmit = (e) => {
        e.preventDefault();
        post('/dev/login-link', { preserveScroll: true });
    };

    const copyToClipboard = (text) => {
        navigator.clipboard.writeText(text);
        setCopiedLink(true);
        setTimeout(() => setCopiedLink(false), 2000);
    };

    const labelClass = "block text-sm font-medium text-neutral-700 dark:text-neutral-300 mb-1";
    const hintClass = "mt-1 text-xs text-neutral-500 dark:text-neutral-400";

    return (
        <Layout>
            <div className="w-full max-w-2xl mx-auto px-4 md:px-8 py-10 flex flex-col gap-6">

                {/* Dev warning banner */}
                <div className="flex gap-3 px-4 py-3 border border-warning/40 bg-warning/5">
                    <svg className="h-5 w-5 text-warning shrink-0 mt-0.5" viewBox="0 0 20 20" fill="currentColor">
                        <path fillRule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clipRule="evenodd" />
                    </svg>
                    <div>
                        <p className="text-sm font-medium text-warning">Development Mode Only</p>
                        <p className="text-sm text-neutral-600 dark:text-neutral-400 mt-0.5">
                            This login method is only available in local development. Login links expire after a configured time period.
                        </p>
                    </div>
                </div>

                {/* Main card */}
                <div className="border border-neutral-200 dark:border-neutral-700">

                    {/* Card Header */}
                    <div className="bg-secondary dark:bg-neutral-800 border-b border-neutral-200 dark:border-neutral-700 px-6 py-4">
                        <h1 className="text-lg font-semibold text-white">Development Login</h1>
                    </div>

                    <div className="bg-white dark:bg-neutral-800 p-6 flex flex-col gap-6">

                        {/* Generate link form */}
                        <form onSubmit={handleSubmit} className="flex flex-col gap-4">
                            <div>
                                <label htmlFor="user" className={labelClass}>Select User</label>
                                <Select
                                    id="user"
                                    value={data.email}
                                    onChange={(e) => setData('email', e.target.value)}
                                    disabled={processing}
                                >
                                    {users.length === 0 && (
                                        <option>No users found — create one first</option>
                                    )}
                                    {users.map((user) => (
                                        <option key={user.id} value={user.email}>
                                            {user.name} ({user.email}) — {user.roles?.map(r => r.name).join(', ') || 'No role'}
                                        </option>
                                    ))}
                                </Select>
                                <p className={hintClass}>Select a user to generate a login link.</p>
                            </div>

                            <div>
                                <Button type="submit" disabled={processing || users.length === 0}>
                                    Generate Login Link
                                </Button>
                            </div>
                        </form>

                        {/* Generated link */}
                        {flash?.loginLink && (
                            <div className="border border-success/30 bg-success/5 p-4 flex flex-col gap-3">
                                <p className="text-sm font-medium text-success">Login link generated!</p>
                                <div className="flex items-start gap-2">
                                    <code className="flex-1 text-xs font-mono bg-white dark:bg-neutral-900 border border-neutral-200 dark:border-neutral-700 px-3 py-2 break-all text-neutral-700 dark:text-neutral-300">
                                        {flash.loginLink}
                                    </code>
                                    <Button
                                        type="button"
                                        variant="secondary"
                                        onClick={() => copyToClipboard(flash.loginLink)}
                                        className="shrink-0"
                                    >
                                        {copiedLink ? 'Copied!' : 'Copy'}
                                    </Button>
                                </div>
                                <div>
                                    <a
                                        href={flash.loginLink}
                                        className="inline-block px-4 py-2 text-sm font-medium bg-success text-white hover:bg-success/90 transition-colors"
                                    >
                                        Click to Login
                                    </a>
                                </div>
                            </div>
                        )}

                        {/* Tinker instructions */}
                        <div className="border-t border-neutral-200 dark:border-neutral-700 pt-6 flex flex-col gap-3">
                            <h3 className="text-sm font-medium text-neutral-900 dark:text-neutral-100">Create Test Users</h3>
                            <p className="text-sm text-neutral-600 dark:text-neutral-400">
                                Use tinker to create test users with different roles:
                            </p>
                            <pre className="text-xs text-neutral-800 dark:text-neutral-200 bg-neutral-50 dark:bg-neutral-900 border border-neutral-200 dark:border-neutral-700 p-4 overflow-x-auto">
                                {`php artisan tinker

                                // Create admin user
                                $admin = User::create([
                                    'name' => 'Admin User',
                                    'email' => 'admin@example.com',
                                    'vatsim_cid' => '1000001',
                                ]);
                                $admin->assignRole('admin');

                                // Create moderator user
                                $mod = User::create([
                                    'name' => 'Moderator User',
                                    'email' => 'mod@example.com',
                                    'vatsim_cid' => '1000002',
                                ]);
                                $mod->assignRole('moderator');

                                // Create regular user
                                $user = User::create([
                                    'name' => 'Regular User',
                                    'email' => 'user@example.com',
                                    'vatsim_cid' => '1000003',
                                ]);
                                $user->assignRole('user');`}
                            </pre>
                        </div>

                        {/* VATSIM OAuth */}
                        <div className="border-t border-neutral-200 dark:border-neutral-700 pt-6 flex flex-col gap-3">
                            <h3 className="text-sm font-medium text-neutral-900 dark:text-neutral-100">Alternative: VATSIM OAuth</h3>
                            <p className="text-sm text-neutral-600 dark:text-neutral-400">
                                You can also use the regular VATSIM OAuth login if configured:
                            </p>
                            <div>
                                <a
                                    href="/auth/vatsim"
                                    className="inline-block px-4 py-2 text-sm font-medium border border-neutral-300 dark:border-neutral-600 text-neutral-700 dark:text-neutral-300 bg-white dark:bg-neutral-900 hover:border-secondary dark:hover:border-primary transition-colors"
                                >
                                    Login with VATSIM
                                </a>
                            </div>
                        </div>

                    </div>
                </div>
            </div>
        </Layout>
    );
}