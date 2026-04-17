import { useForm, usePage, Head } from '@inertiajs/react';
import Layout from '../../Layouts/Layout';
import Button from '../../Components/Button';
import Input from '../../Components/Input';
import Select from '../../Components/Select';
import MarkdownEditor from '../../Components/MarkdownEditor';
import AirportSelector from '../../Components/AirportSelector';
import Flatpickr from 'react-flatpickr';
import 'flatpickr/dist/flatpickr.min.css';
import { useState, useEffect, useMemo } from 'react';
import Card from '../../Components/Card';

// Convert Flatpickr output (dd-mm-yyyy HH:MM) to a naive local ISO string
// (no Z suffix). The server interprets it in the event's selected timezone.
const toLocalIso = (dateStr) => {
    if (!dateStr) return '';

    const [datePart, timePart] = dateStr.split(' ');
    const [day, month, year] = datePart.split('-');

    return `${year}-${month}-${day}T${timePart}:00`;
};
const toDisplayStr = (iso) => {
    if (!iso) return undefined;

    const [datePart, timePart] = iso.slice(0, 16).split('T');
    const [year, month, day] = datePart.split('-');

    return `${day}-${month}-${year} ${timePart}`;
};

const initRecurrenceFreq = (rrule) => {
    if (!rrule) return 'WEEKLY';
    if (/FREQ=MONTHLY/.test(rrule)) return 'MONTHLY';
    if (/INTERVAL=2/.test(rrule)) return 'BIWEEKLY';
    return 'WEEKLY';
};

const initRecurrenceUntil = (rrule) => {
    if (!rrule) return '';
    const match = rrule.match(/UNTIL=(\d{4})(\d{2})(\d{2})/);
    return match ? `${match[1]}-${match[2]}-${match[3]}` : '';
};

const DOW_MAP = ['SU', 'MO', 'TU', 'WE', 'TH', 'FR', 'SA'];
const getDayOfWeek = (isoDateStr) => {
    if (!isoDateStr) return null;
    return DOW_MAP[new Date(isoDateStr).getUTCDay()];
};

const flatpickrBaseOptions = {
    enableTime: true,
    dateFormat: 'd-m-Y H:i',
    time_24hr: true,
    minuteIncrement: 1,
};

const labelClass = "block text-sm font-medium text-neutral-700 dark:text-neutral-300 mb-1";
const hintClass = "mt-1 text-xs text-neutral-500 dark:text-neutral-400";
const sectionClass = "flex flex-col gap-1";
const inputClass = "w-full px-3 py-2 text-sm bg-white dark:bg-neutral-900 text-neutral-900 dark:text-neutral-100 border border-neutral-300 dark:border-neutral-700 focus:outline-none focus:border-primary dark:focus:border-primary transition-colors";

export default function Edit({ event, calendars: { data: calendars } }) {
    const evt = event.data ?? event;

    const { auth } = usePage().props;
    const [showRecurrence, setShowRecurrence] = useState(!!evt.recurrence_rule);
    const [previewUrl, setPreviewUrl] = useState(evt.banner_url ?? null);
    const defaultStartDate = toDisplayStr(evt.start_datetime?.local);
    const defaultEndDate = toDisplayStr(evt.end_datetime?.local);
    const [discordChannels, setDiscordChannels] = useState([]);
    const [loadingChannels, setLoadingChannels] = useState(false);

    const [recurrenceFreq, setRecurrenceFreq] = useState(initRecurrenceFreq(evt.recurrence_rule));
    const [recurrenceUntil, setRecurrenceUntil] = useState(initRecurrenceUntil(evt.recurrence_rule));

    const { data, setData, post, processing, errors } = useForm({
        _method: 'PUT',
        calendar_id: evt.calendar.id || '',
        title: evt.title || '',
        short_description: evt.short_description || '',
        long_description: evt.long_description || '',
        featured_airports: evt.featured_airports || [],
        timezone: evt.timezone || 'UTC',
        start_datetime: evt.start_datetime?.local || '',
        end_datetime: evt.end_datetime?.local || '',
        banner: null,
        recurrence_rule: evt.recurrence_rule || '',
        discord_channel_id: evt.discord_channel_id || '',
        status: evt.status || 'draft',
    });

    useEffect(() => {
        setLoadingChannels(true);
        fetch('/api/v2/discord/channels')
            .then(response => response.json())
            .then(data => {
                setDiscordChannels(data);
                setLoadingChannels(false);
            })
            .catch(() => setLoadingChannels(false));
    }, []);

    const handleSubmit = (e) => {
        e.preventDefault();
        post(`/events/${evt.slug}`, { forceFormData: true });
    };

    const handleBannerChange = (e) => {
        const file = e.target.files[0];
        if (file) {
            setData('banner', file);
            const reader = new FileReader();
            reader.onloadend = () => setPreviewUrl(reader.result);
            reader.readAsDataURL(file);
        }
    };

    useEffect(() => {
        if (showRecurrence) {
            const dow = getDayOfWeek(data.start_datetime);
            const parts = [];
            if (recurrenceFreq === 'MONTHLY') {
                parts.push('FREQ=MONTHLY');
            } else {
                parts.push('FREQ=WEEKLY');
                if (recurrenceFreq === 'BIWEEKLY') parts.push('INTERVAL=2');
                if (dow) parts.push(`BYDAY=${dow}`);
            }
            if (recurrenceUntil) parts.push(`UNTIL=${recurrenceUntil.replace(/-/g, '')}T000000Z`);
            setData('recurrence_rule', parts.join(';'));
        } else {
            setData('recurrence_rule', '');
        }
    }, [recurrenceFreq, recurrenceUntil, showRecurrence, data.start_datetime]);

    const startOptions = useMemo(() => ({
        ...flatpickrBaseOptions,
        onChange: ([_date], dateStr) => setData('start_datetime', toLocalIso(dateStr)),
    }), [setData]);

    const endOptions = useMemo(() => ({
        ...flatpickrBaseOptions,
        onChange: ([_date], dateStr) => setData('end_datetime', toLocalIso(dateStr)),
    }), [setData]);

    return (
        <Layout auth={auth} className="">
            <Head title={`Edit ${evt.title}`} />

            <Card title="Edit Event">
                <form onSubmit={handleSubmit} className='flex flex-col gap-6 p-6'>
                    <div className={sectionClass}>
                        <label htmlFor="calendar_id" className={labelClass}>Calendar <span className='text-danger'>*</span></label>
                        <Select
                            id="calendar_id"
                            value={data.calendar_id}
                            onChange={(e) => setData('calendar_id', e.target.value)}
                            error={errors.calendar_id}
                            required
                        >
                            {calendars.map((calendar) => (
                                <option key={calendar.id} value={calendar.id}>{calendar.title}</option>
                            ))}
                        </Select>
                    </div>
                    
                    <div className={sectionClass}>
                        <label htmlFor="title" className={labelClass}>Event Title <span className='text-danger'>*</span></label>
                        <Input
                            id="title"
                            value={data.title}
                            onChange={(e) => setData('title', e.target.value)}
                            error={errors.title}
                            required
                        />
                    </div>

                    <div className={sectionClass}>
                        <label htmlFor="short_description" className={labelClass}>
                            Short Description <span className='text-danger'>*</span> <span className="font-normal text-neutral-400">(for Discord)</span>
                        </label>
                        <MarkdownEditor
                            value={data.short_description}
                            onChange={(value) => setData('short_description', value)}
                            error={errors.short_description}
                            placeholder="Enter short description for Discord notifications (markdown supported)..."
                        />
                        <p className={hintClass}>Appears in Discord notifications. Markdown supported.</p>
                    </div>

                    <div className={sectionClass}>
                        <label htmlFor="long_description" className={labelClass}>Long Description <span className='text-danger'>*</span></label>
                        <MarkdownEditor
                            value={data.long_description}
                            onChange={(value) => setData('long_description', value)}
                            error={errors.long_description}
                            placeholder="Enter detailed event description (markdown supported)..."
                        />
                    </div>

                    <div className={sectionClass}>
                        <label htmlFor="featured_airports" className={labelClass}>Featured Airports / Facilities</label>
                        <AirportSelector
                            value={data.featured_airports}
                            onChange={(airports) => setData('featured_airports', airports)}
                            error={errors.featured_airports}
                        />
                        <p className={hintClass}>Add ICAO codes for airports or facilities featured in this event.</p>
                    </div>

                    <div className={sectionClass}>
                        <label htmlFor="discord_channel_id" className={labelClass}>Discord Channel</label>
                        <Select
                            id="discord_channel_id"
                            value={data.discord_channel_id}
                            onChange={(e) => setData('discord_channel_id', e.target.value)}
                            error={errors.discord_channel_id}
                        >
                            <option value="">No Discord channel</option>
                            {loadingChannels ? (
                                <option disabled>Loading channels...</option>
                            ) : (
                                discordChannels.map((channel) => (
                                    <option key={channel.id} value={channel.id}>#{channel.name}</option>
                                ))
                            )}
                        </Select>
                        <p className={hintClass}>Select a Discord channel for staffing messages (optional).</p>
                    </div>

                    <div className={sectionClass}>
                        <label htmlFor="status" className={labelClass}>Status <span className="text-danger">*</span></label>
                        <Select
                            id="status"
                            value={data.status}
                            onChange={(e) => setData('status', e.target.value)}
                            error={errors.status}
                            required
                        >
                            <option value="published">Published</option>
                            <option value="draft">Draft</option>
                        </Select>
                    </div>

                    <div className={sectionClass}>
                        <label htmlFor="timezone" className={labelClass}>Timezone <span className='text-danger'>*</span></label>
                        <Select
                            id="timezone"
                            value={data.timezone}
                            onChange={(e) => setData('timezone', e.target.value)}
                            error={errors.timezone}
                            required
                        >
                            <optgroup label="UTC">
                                <option value="UTC">UTC</option>
                            </optgroup>
                            <optgroup label="Scandinavia">
                                <option value="Europe/Copenhagen">Europe/Copenhagen (DK/NO/SE — CET/CEST)</option>
                                <option value="Europe/Helsinki">Europe/Helsinki (FI/EE — EET/EEST)</option>
                                <option value="Atlantic/Reykjavik">Atlantic/Reykjavik (IS — UTC)</option>
                            </optgroup>
                            <optgroup label="Europe">
                                <option value="Europe/London">Europe/London (GMT/BST)</option>
                                <option value="Europe/Amsterdam">Europe/Amsterdam (CET/CEST)</option>
                                <option value="Europe/Berlin">Europe/Berlin (CET/CEST)</option>
                                <option value="Europe/Paris">Europe/Paris (CET/CEST)</option>
                                <option value="Europe/Madrid">Europe/Madrid (CET/CEST)</option>
                                <option value="Europe/Rome">Europe/Rome (CET/CEST)</option>
                                <option value="Europe/Warsaw">Europe/Warsaw (CET/CEST)</option>
                                <option value="Europe/Athens">Europe/Athens (EET/EEST)</option>
                                <option value="Europe/Moscow">Europe/Moscow (MSK)</option>
                            </optgroup>
                            <optgroup label="Americas">
                                <option value="America/New_York">America/New_York (ET)</option>
                                <option value="America/Chicago">America/Chicago (CT)</option>
                                <option value="America/Denver">America/Denver (MT)</option>
                                <option value="America/Los_Angeles">America/Los_Angeles (PT)</option>
                            </optgroup>
                            <optgroup label="Asia / Pacific">
                                <option value="Asia/Dubai">Asia/Dubai (GST)</option>
                                <option value="Asia/Kolkata">Asia/Kolkata (IST)</option>
                                <option value="Asia/Singapore">Asia/Singapore (SGT)</option>
                                <option value="Asia/Tokyo">Asia/Tokyo (JST)</option>
                                <option value="Australia/Sydney">Australia/Sydney (AEST/AEDT)</option>
                            </optgroup>
                        </Select>
                        <p className={hintClass}>Times are entered and displayed in this timezone. DST is handled automatically for recurring events.</p>
                        {errors.timezone && <p className="mt-1 text-sm text-danger">{errors.timezone}</p>}
                    </div>

                    <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div className={sectionClass}>
                            <label htmlFor="start_datetime" className={labelClass}>Start Date & Time <span className='text-danger'>*</span></label>
                            <Flatpickr
                                defaultValue={defaultStartDate}
                                options={startOptions}
                                className={`${inputClass} ${errors.start_datetime ? 'border-danger' : ''}`}
                                placeholder="DD-MM-YYYY HH:MM"
                            />
                            {errors.start_datetime && (
                                <p className="mt-1 text-sm text-danger">{errors.start_datetime}</p>
                            )}
                        </div>
                        <div className={sectionClass}>
                            <label htmlFor="end_datetime" className={labelClass}>End Date & Time <span className='text-danger'>*</span></label>
                            <Flatpickr
                                defaultValue={defaultEndDate}
                                options={endOptions}
                                className={`${inputClass} ${errors.end_datetime ? 'border-danger' : ''}`}
                                placeholder="DD-MM-YYYY HH:MM"
                            />
                            {errors.end_datetime && (
                                <p className="mt-1 text-sm text-danger">{errors.end_datetime}</p>
                            )}
                        </div>
                    </div>

                    <div className={sectionClass}>
                        <label htmlFor="banner" className={labelClass}>
                            Banner Image <span className="font-normal text-neutral-400">(16:9 ratio)</span>
                        </label>
                        {previewUrl && (
                            <div className="w-full aspect-video overflow-hidden border border-neutral-200 dark:border-neutral-700 mb-2">
                                <img
                                    src={previewUrl}
                                    alt="Banner preview"
                                    className="w-full h-full object-contain bg-neutral-100 dark:bg-neutral-900"
                                />
                            </div>
                        )}
                        <input
                            id="banner"
                            type="file"
                            accept="image/*"
                            onChange={handleBannerChange}
                            className="block w-full text-sm text-neutral-700 dark:text-neutral-300 border border-neutral-300 dark:border-neutral-700 bg-white dark:bg-neutral-900 focus:outline-none focus:border-primary transition-colors file:mr-4 file:py-2 file:px-4 file:border-0 file:text-sm file:font-medium file:bg-secondary file:text-white hover:file:bg-secondary/90"
                        />
                        {errors.banner && (
                            <p className="mt-1 text-sm text-danger">{errors.banner}</p>
                        )}
                        <p className={hintClass}>Leave empty to keep the current banner.</p>
                    </div>

                    <div className={sectionClass}>
                        <label className="flex items-center gap-2 cursor-pointer">
                            <input
                                type="checkbox"
                                checked={showRecurrence}
                                onChange={(e) => {
                                    setShowRecurrence(e.target.checked);
                                    if (!e.target.checked) setData('recurrence_rule', '');
                                }}
                                className="accent-secondary w-4 h-4"
                            />
                            <span className="text-sm font-medium text-neutral-700 dark:text-neutral-300">Recurring Event</span>
                        </label>

                        {showRecurrence && (
                            <div className="mt-3 flex flex-col gap-4 p-4 border border-neutral-200 dark:border-neutral-700 bg-neutral-50 dark:bg-neutral-700/30">
                                <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                    <div className={sectionClass}>
                                        <label className={labelClass}>Frequency</label>
                                        <Select
                                            value={recurrenceFreq}
                                            onChange={(e) => setRecurrenceFreq(e.target.value)}
                                        >
                                            <option value="WEEKLY">Weekly</option>
                                            <option value="BIWEEKLY">Bi-weekly</option>
                                            <option value="MONTHLY">Monthly</option>
                                        </Select>
                                        <p className={hintClass}>Day of week is derived from the start date.</p>
                                    </div>
                                    <div className={sectionClass}>
                                        <label className={labelClass}>Repeat Until</label>
                                        <Input
                                            type="date"
                                            value={recurrenceUntil}
                                            onChange={(e) => setRecurrenceUntil(e.target.value)}
                                        />
                                        <p className={hintClass}>Leave empty for no end date.</p>
                                    </div>
                                </div>

                                {evt.recurrence_rule && (
                                    <p className="text-xs text-neutral-500 dark:text-neutral-400 pt-2 border-t border-neutral-200 dark:border-neutral-700">
                                        Note: Changing recurrence rules only affects future instances.
                                    </p>
                                )}
                            </div>
                        )}
                    </div>

                    <div className="flex justify-end gap-3 pt-2 border-t border-neutral-200 dark:border-neutral-700">
                        <Button
                            type="button"
                            variant="secondary"
                            onClick={() => window.history.back()}
                        >
                            Cancel
                        </Button>
                        <Button type="submit" variant="success" disabled={processing}>
                            {processing ? 'Saving...' : 'Update Event'}
                        </Button>
                    </div>
                </form>
            </Card>
        </Layout>
    );
}