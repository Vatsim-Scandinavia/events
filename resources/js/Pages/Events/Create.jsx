import { useForm, usePage, Head } from '@inertiajs/react';
import Layout from '../../Layouts/Layout';
import Button from '../../Components/Button';
import Input from '../../Components/Input';
import Select from '../../Components/Select';
import AirportSelector from '../../Components/AirportSelector';
import MarkdownEditor from '../../Components/MarkdownEditor';
import Flatpickr from 'react-flatpickr';
import 'flatpickr/dist/flatpickr.min.css';
import { useState, useEffect, useMemo } from 'react';

const toUtcIso = (dateStr) => dateStr ? dateStr.replace(' ', 'T') + ':00Z' : '';

export default function Create({ calendars, preselectedCalendarId }) {
    const { auth } = usePage().props;
    const [showRecurrence, setShowRecurrence] = useState(false);
    const [discordChannels, setDiscordChannels] = useState([]);
    const [loadingChannels, setLoadingChannels] = useState(false);
    const [recurrence, setRecurrence] = useState({
        freq: 'WEEKLY',
        interval: 1,
        count: '',
        until: '',
        byDay: []
    });

    const { data, setData, post, processing, errors } = useForm({
        calendar_id: preselectedCalendarId || calendars[0]?.id || '',
        title: '',
        short_description: '',
        long_description: '',
        featured_airports: [],
        start_datetime: '',
        end_datetime: '',
        banner: null,
        recurrence_rule: '',
        discord_staffing_channel_id: '',
    });

    useEffect(() => {
        setLoadingChannels(true);
        fetch('/api/discord/channels')
            .then(response => response.json())
            .then(data => {
                setDiscordChannels(data);
                setLoadingChannels(false);
            })
            .catch(() => setLoadingChannels(false));
    }, []);

    const handleSubmit = (e) => {
        e.preventDefault();
        post('/events', { forceFormData: true });
    };

    const handleStartDateChange = ([_date], dateStr) => {
        setData('start_datetime', toUtcIso(dateStr));
    };

    const handleEndDateChange = ([_date], dateStr) => {
        setData('end_datetime', toUtcIso(dateStr));
    };

    useEffect(() => {
        if (showRecurrence) {
            const parts = [`FREQ=${recurrence.freq}`];
            if (recurrence.interval > 1) parts.push(`INTERVAL=${recurrence.interval}`);
            if (recurrence.count) {
                parts.push(`COUNT=${recurrence.count}`);
            } else if (recurrence.until) {
                parts.push(`UNTIL=${recurrence.until.replace(/-/g, '')}T000000Z`);
            }
            if (recurrence.byDay.length > 0) parts.push(`BYDAY=${recurrence.byDay.join(',')}`);
            setData('recurrence_rule', parts.join(';'));
        } else {
            setData('recurrence_rule', '');
        }
    }, [recurrence, showRecurrence]);

    const weekdays = [
        { value: 'MO', label: 'Mon' }, { value: 'TU', label: 'Tue' },
        { value: 'WE', label: 'Wed' }, { value: 'TH', label: 'Thu' },
        { value: 'FR', label: 'Fri' }, { value: 'SA', label: 'Sat' },
        { value: 'SU', label: 'Sun' },
    ];

    const toggleWeekday = (day) => {
        setRecurrence(prev => ({
            ...prev,
            byDay: prev.byDay.includes(day)
                ? prev.byDay.filter(d => d !== day)
                : [...prev.byDay, day]
        }));
    };

    const baseOptions = {
        enableTime: true,
        dateFormat: 'd-m-Y H:i',
        time_24hr: true,
        minuteIncrement: 5,
    };

    const startOptions = useMemo(() => ({
        ...baseOptions,
        onChange: ([_date], dateStr) => setData('start_datetime', toUtcIso(dateStr)),
    }), []);

    const endOptions = useMemo(() => ({
        ...baseOptions,
        onChange: ([_date], dateStr) => setData('end_datetime', toUtcIso(dateStr)),
    }), []);

    const labelClass = "block text-sm font-medium text-neutral-700 dark:text-neutral-300 mb-1";
    const hintClass = "mt-1 text-xs text-neutral-500 dark:text-neutral-400";
    const sectionClass = "flex flex-col gap-1";
    const inputClass = "w-full px-3 py-2 text-sm bg-white dark:bg-neutral-900 text-neutral-900 dark:text-neutral-100 border border-neutral-300 dark:border-neutral-700 focus:outline-none focus:border-primary dark:focus:border-primary transition-colors";

    return (
        <Layout auth={auth}>
            <Head title="Create Event" />
            <Layout auth={auth}>
                <div className="w-full max-w-7xl mx-auto px-4 md:px-8 py-10">
                    <div className="border border-neutral-200 dark:border-neutral-700">

                        {/* Card Header */}
                        <div className="bg-secondary dark:bg-neutral-800 border-b border-neutral-200 dark:border-neutral-700 px-6 py-4">
                            <h1 className="text-lg font-semibold text-white dark:text-neutral-100">Create Event</h1>
                        </div>

                        {/* Form Body */}
                        <div className="bg-white dark:bg-neutral-800 p-6">
                            <form onSubmit={handleSubmit} className="flex flex-col gap-6">

                                {/* Calendar */}
                                <div className={sectionClass}>
                                    <label htmlFor="calendar_id" className={labelClass}>Calendar *</label>
                                    <Select
                                        id="calendar_id"
                                        value={data.calendar_id}
                                        onChange={(e) => setData('calendar_id', e.target.value)}
                                        error={errors.calendar_id}
                                        required
                                    >
                                        {calendars.map((calendar) => (
                                            <option key={calendar.id} value={calendar.id}>{calendar.name}</option>
                                        ))}
                                    </Select>
                                </div>

                                {/* Title */}
                                <div className={sectionClass}>
                                    <label htmlFor="title" className={labelClass}>Event Title *</label>
                                    <Input
                                        id="title"
                                        value={data.title}
                                        onChange={(e) => setData('title', e.target.value)}
                                        error={errors.title}
                                        required
                                    />
                                </div>

                                {/* Short Description */}
                                <div className={sectionClass}>
                                    <label htmlFor="short_description" className={labelClass}>
                                        Short Description * <span className="font-normal text-neutral-400">(for Discord)</span>
                                    </label>
                                    <MarkdownEditor
                                        value={data.short_description}
                                        onChange={(value) => setData('short_description', value)}
                                        error={errors.short_description}
                                        placeholder="Enter short description for Discord notifications (markdown supported)..."
                                    />
                                    <p className={hintClass}>Appears in Discord notifications. Markdown supported.</p>
                                </div>

                                {/* Long Description */}
                                <div className={sectionClass}>
                                    <label htmlFor="long_description" className={labelClass}>Long Description *</label>
                                    <MarkdownEditor
                                        value={data.long_description}
                                        onChange={(value) => setData('long_description', value)}
                                        error={errors.long_description}
                                        placeholder="Enter detailed event description (markdown supported)..."
                                    />
                                </div>

                                {/* Featured Airports */}
                                <div className={sectionClass}>
                                    <label htmlFor="featured_airports" className={labelClass}>Featured Airports / Facilities</label>
                                    <AirportSelector
                                        value={data.featured_airports}
                                        onChange={(airports) => setData('featured_airports', airports)}
                                        error={errors.featured_airports}
                                    />
                                    <p className={hintClass}>Add ICAO codes for airports or facilities featured in this event.</p>
                                </div>

                                {/* Discord Channel */}
                                <div className={sectionClass}>
                                    <label htmlFor="discord_staffing_channel_id" className={labelClass}>Discord Staffing Channel</label>
                                    <Select
                                        id="discord_staffing_channel_id"
                                        value={data.discord_staffing_channel_id}
                                        onChange={(e) => setData('discord_staffing_channel_id', e.target.value)}
                                        error={errors.discord_staffing_channel_id}
                                    >
                                        <option value="">No Discord channel</option>
                                        {loadingChannels ? (
                                            <option disabled>Loading channels...</option>
                                        ) : (
                                            discordChannels.map((guild) => (
                                                <optgroup key={guild.guild_id} label={guild.guild_name}>
                                                    {guild.channels.map((channel) => (
                                                        <option key={channel.id} value={channel.id}>#{channel.name}</option>
                                                    ))}
                                                </optgroup>
                                            ))
                                        )}
                                    </Select>
                                    <p className={hintClass}>Select a Discord channel for staffing messages (optional).</p>
                                </div>

                                {/* Date & Time */}
                                <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                    <div className={sectionClass}>
                                        <label htmlFor="start_datetime" className={labelClass}>Start Date & Time (UTC) *</label>
                                        <Flatpickr
                                            options={startOptions}
                                            className={`${inputClass} ${errors.start_datetime ? 'border-danger' : ''}`}
                                            placeholder="DD-MM-YYYY HH:MM"
                                        />
                                        {errors.start_datetime && (
                                            <p className="mt-1 text-sm text-danger">{errors.start_datetime}</p>
                                        )}
                                    </div>
                                    <div className={sectionClass}>
                                        <label htmlFor="end_datetime" className={labelClass}>End Date & Time (UTC) *</label>
                                        <Flatpickr
                                            options={endOptions}
                                            className={`${inputClass} ${errors.end_datetime ? 'border-danger' : ''}`}
                                            placeholder="DD-MM-YYYY HH:MM"
                                        />
                                        {errors.end_datetime && (
                                            <p className="mt-1 text-sm text-danger">{errors.end_datetime}</p>
                                        )}
                                    </div>
                                </div>

                                {/* Banner */}
                                <div className={sectionClass}>
                                    <label htmlFor="banner" className={labelClass}>Banner Image <span className="font-normal text-neutral-400">(16:9 ratio)</span></label>
                                    <input
                                        id="banner"
                                        type="file"
                                        accept="image/*"
                                        onChange={(e) => setData('banner', e.target.files[0])}
                                        className="block w-full text-sm text-neutral-700 dark:text-neutral-300 border border-neutral-300 dark:border-neutral-700 bg-white dark:bg-neutral-900 focus:outline-none focus:border-primary transition-colors file:mr-4 file:py-2 file:px-4 file:border-0 file:text-sm file:font-medium file:bg-secondary file:text-white hover:file:bg-secondary/90 dark:file:bg-primary dark:file:text-neutral-900"
                                    />
                                    {errors.banner && (
                                        <p className="mt-1 text-sm text-danger">{errors.banner}</p>
                                    )}
                                </div>

                                {/* Recurrence */}
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
                                                        value={recurrence.freq}
                                                        onChange={(e) => setRecurrence({ ...recurrence, freq: e.target.value })}
                                                    >
                                                        <option value="DAILY">Daily</option>
                                                        <option value="WEEKLY">Weekly</option>
                                                        <option value="MONTHLY">Monthly</option>
                                                        <option value="YEARLY">Yearly</option>
                                                    </Select>
                                                </div>
                                                <div className={sectionClass}>
                                                    <label className={labelClass}>Repeat Every</label>
                                                    <Input
                                                        type="number"
                                                        min="1"
                                                        value={recurrence.interval}
                                                        onChange={(e) => setRecurrence({ ...recurrence, interval: parseInt(e.target.value) || 1 })}
                                                    />
                                                </div>
                                            </div>

                                            {recurrence.freq === 'WEEKLY' && (
                                                <div className={sectionClass}>
                                                    <label className={labelClass}>Repeat On</label>
                                                    <div className="flex flex-wrap gap-2">
                                                        {weekdays.map(day => (
                                                            <button
                                                                key={day.value}
                                                                type="button"
                                                                onClick={() => toggleWeekday(day.value)}
                                                                className={`px-3 py-2 text-sm font-medium border transition-colors ${
                                                                    recurrence.byDay.includes(day.value)
                                                                        ? 'bg-secondary text-white border-secondary'
                                                                        : 'bg-white dark:bg-neutral-800 text-neutral-700 dark:text-neutral-300 border-neutral-300 dark:border-neutral-600 hover:border-secondary dark:hover:border-primary'
                                                                }`}
                                                            >
                                                                {day.label}
                                                            </button>
                                                        ))}
                                                    </div>
                                                </div>
                                            )}

                                            <div className={sectionClass}>
                                                <label className={labelClass}>End Condition</label>
                                                <div className="flex flex-col gap-3">
                                                    <label className="flex items-center gap-2">
                                                        <input
                                                            type="radio"
                                                            name="endCondition"
                                                            checked={!!recurrence.count}
                                                            onChange={() => setRecurrence({ ...recurrence, count: '10', until: '' })}
                                                            className="accent-secondary w-4 h-4"
                                                        />
                                                        <span className="text-sm text-neutral-700 dark:text-neutral-300">After</span>
                                                        <Input
                                                            type="number"
                                                            min="1"
                                                            value={recurrence.count}
                                                            onChange={(e) => setRecurrence({ ...recurrence, count: e.target.value, until: '' })}
                                                            className="w-20"
                                                            disabled={!recurrence.count}
                                                        />
                                                        <span className="text-sm text-neutral-700 dark:text-neutral-300">occurrences</span>
                                                    </label>
                                                    <label className="flex items-center gap-2">
                                                        <input
                                                            type="radio"
                                                            name="endCondition"
                                                            checked={!!recurrence.until}
                                                            onChange={() => setRecurrence({ ...recurrence, count: '', until: new Date().toISOString().split('T')[0] })}
                                                            className="accent-secondary w-4 h-4"
                                                        />
                                                        <span className="text-sm text-neutral-700 dark:text-neutral-300">On date</span>
                                                        <Input
                                                            type="date"
                                                            value={recurrence.until}
                                                            onChange={(e) => setRecurrence({ ...recurrence, count: '', until: e.target.value })}
                                                            disabled={!recurrence.until}
                                                        />
                                                    </label>
                                                </div>
                                            </div>
                                        </div>
                                    )}
                                </div>

                                {/* Actions */}
                                <div className="flex justify-end gap-3 pt-2 border-t border-neutral-200 dark:border-neutral-700">
                                    <Button
                                        type="button"
                                        variant="secondary"
                                        onClick={() => window.history.back()}
                                    >
                                        Cancel
                                    </Button>
                                    <Button type="submit" variant="success" disabled={processing}>
                                        {processing ? 'Creating...' : 'Create Event'}
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