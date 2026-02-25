import { useForm, usePage, Head } from '@inertiajs/react';
import Layout from '../../Layouts/Layout';
import Button from '../../Components/Button';
import Input from '../../Components/Input';
import Textarea from '../../Components/Textarea';

export default function Create() {
    const { auth } = usePage().props;
    const { data, setData, post, processing, errors } = useForm({
        name: '',
        description: '',
        is_public: true,
    });

    const handleSubmit = (e) => {
        e.preventDefault();
        post('/calendars');
    };

    const labelClass = "block text-sm font-medium text-neutral-700 dark:text-neutral-300 mb-1";
    const sectionClass = "flex flex-col gap-1";

    return (
        <>
            <Head title="Create Calendar" />
            <Layout auth={auth}>
                <div className="w-full max-w-7xl mx-auto px-4 md:px-8 py-10">
                    <div className="border border-neutral-200 dark:border-neutral-700">

                        {/* Card Header */}
                        <div className="bg-secondary dark:bg-neutral-800 border-b border-neutral-200 dark:border-neutral-700 px-6 py-4">
                            <h1 className="text-lg font-semibold text-white">Create Calendar</h1>
                        </div>

                        {/* Form Body */}
                        <div className="bg-white dark:bg-neutral-800 p-6">
                            <form onSubmit={handleSubmit} className="flex flex-col gap-6">

                                <div className={sectionClass}>
                                    <label htmlFor="name" className={labelClass}>Calendar Name *</label>
                                    <Input
                                        id="name"
                                        type="text"
                                        value={data.name}
                                        onChange={(e) => setData('name', e.target.value)}
                                        error={errors.name}
                                        required
                                    />
                                </div>

                                <div className={sectionClass}>
                                    <label htmlFor="description" className={labelClass}>Description</label>
                                    <Textarea
                                        id="description"
                                        value={data.description}
                                        onChange={(e) => setData('description', e.target.value)}
                                        error={errors.description}
                                        rows={4}
                                    />
                                </div>

                                <div>
                                    <label className="flex items-center gap-2 cursor-pointer">
                                        <input
                                            type="checkbox"
                                            checked={data.is_public}
                                            onChange={(e) => setData('is_public', e.target.checked)}
                                            className="accent-secondary w-4 h-4"
                                        />
                                        <span className="text-sm text-neutral-700 dark:text-neutral-300">
                                            Public calendar (visible to everyone)
                                        </span>
                                    </label>
                                </div>

                                <div className="flex justify-end gap-3 pt-2 border-t border-neutral-200 dark:border-neutral-700">
                                    <Button
                                        type="button"
                                        variant="secondary"
                                        onClick={() => window.history.back()}
                                    >
                                        Cancel
                                    </Button>
                                    <Button variant="success" type="submit" disabled={processing}>
                                        {processing ? 'Creating...' : 'Create Calendar'}
                                    </Button>
                                </div>

                            </form>
                        </div>
                    </div>
                </div>
            </Layout>
        </>
    );
}