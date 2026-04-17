import { useForm, usePage, Head } from '@inertiajs/react';
import Layout from '../../Layouts/Layout';
import Button from '../../Components/Button';
import Input from '../../Components/Input';
import Textarea from '../../Components/Textarea';
import Card from '../../Components/Card';

export default function Edit({ calendar }) {
    const { auth } = usePage().props;
    const { data, setData, put, processing, errors } = useForm({
        title: calendar.data.title || '',
        description: calendar.data.description || '',
        visibility: calendar.data.visibility,
    });

    const handleSubmit = (e) => {
        e.preventDefault();
        put(`/calendars/${calendar.data.id}`);
    };

    const labelClass = "block text-sm font-medium text-neutral-700 dark:text-neutral-300 mb-1";
    const sectionClass = "flex flex-col gap-1";

    return (
        <>
            <Head title={`Edit ${calendar.data.title}`} />
            <Layout auth={auth} className="">
                <Card title={`Edit Calendar`} subtitle={`Use the form below to edit your calendar. You can update the title, description, and visibility of the calendar.`}>
                    <div className="p-6">
                        <form onSubmit={handleSubmit} className="flex flex-col gap-6">

                            <div className={sectionClass}>
                                <label htmlFor="title" className={labelClass}>Calendar Title *</label>
                                <Input
                                    id="title"
                                    type="text"
                                    value={data.title}
                                    onChange={(e) => setData('title', e.target.value)}
                                    error={errors.title}
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
                                        checked={data.visibility === 'public'}
                                        onChange={(e) => setData('visibility', e.target.checked ? 'public' : 'private')}
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
                                    {processing ? 'Saving...' : 'Update Calendar'}
                                </Button>
                            </div>

                        </form>
                    </div>
                </Card>
            </Layout>
        </>
    );
}