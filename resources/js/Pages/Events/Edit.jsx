import { useForm, usePage, Head } from '@inertiajs/react';
import Layout from '../../Layouts/Layout';
import Button from '../../Components/Button';
import Input from '../../Components/Input';
import Textarea from '../../Components/Textarea';
import Select from '../../Components/Select';
import AirportSelector from '../../Components/AirportSelector';
import MarkdownEditor from '../../Components/MarkdownEditor';
import DateTimePicker from '../../Components/DateTimePicker';
import { useState, useEffect } from 'react';

export default function Edit({ event, calendars, bannerUrl }) {
    const { auth } = usePage().props;
    const [showRecurrence, setShowRecurrence] = useState(!!event.recurrence_rule);
    const [previewUrl, setPreviewUrl] = useState(bannerUrl);
    const [startDate, setStartDate] = useState(event.start_datetime ? new Date(event.start_datetime) : null);
    const [endDate, setEndDate] = useState(event.end_datetime ? new Date(event.end_datetime) : null);
    const [discordChannels, setDiscordChannels] = useState([]);
    const [loadingChannels, setLoadingChannels] = useState(false);
    
    // Parse existing recurrence rule
    const parseRRule = (rrule) => {
        if (!rrule) return { freq: 'WEEKLY', interval: 1, count: '', until: '', byDay: [] };
        
        const parts = rrule.split(';');
        const parsed = { freq: 'WEEKLY', interval: 1, count: '', until: '', byDay: [] };
        
        parts.forEach(part => {
            const [key, value] = part.split('=');
            if (key === 'FREQ') parsed.freq = value;
            if (key === 'INTERVAL') parsed.interval = parseInt(value);
            if (key === 'COUNT') parsed.count = value;
            if (key === 'UNTIL') {
                // Convert UNTIL format (20260201T000000Z) to date input format (YYYY-MM-DD)
                parsed.until = value.substring(0, 8).replace(/(\d{4})(\d{2})(\d{2})/, '$1-$2-$3');
            }
            if (key === 'BYDAY') parsed.byDay = value.split(',');
        });
        
        return parsed;
    };
    
    const [recurrence, setRecurrence] = useState(parseRRule(event.recurrence_rule));
    
    const { data, setData, post, processing, errors } = useForm({
        _method: 'PUT',
        calendar_id: event.calendar_id || '',
        title: event.title || '',
        short_description: event.short_description || '',
        long_description: event.long_description || '',
        featured_airports: event.featured_airports || [],
        start_datetime: event.start_datetime || '',
        end_datetime: event.end_datetime || '',
        banner: null,
        recurrence_rule: event.recurrence_rule || '',
        discord_staffing_channel_id: event.discord_staffing_channel_id || '',
    });

    // Load Discord channels
    useEffect(() => {
        setLoadingChannels(true);
        fetch('/api/discord/channels')
            .then(response => response.json())
            .then(data => {
                setDiscordChannels(data);
                setLoadingChannels(false);
            })
            .catch(() => {
                setLoadingChannels(false);
            });
    }, []);

    const handleSubmit = (e) => {
        e.preventDefault();
        post(`/events/${event.id}`, {
            forceFormData: true,
        });
    };

    const handleBannerChange = (e) => {
        const file = e.target.files[0];
        if (file) {
            setData('banner', file);
            // Create preview
            const reader = new FileReader();
            reader.onloadend = () => {
                setPreviewUrl(reader.result);
            };
            reader.readAsDataURL(file);
        }
    };

    const handleStartDateChange = (date) => {
        setStartDate(date);
        if (date) {
            // Format as ISO8601 for Laravel
            setData('start_datetime', date.toISOString());
            
            // If end date is before start date, adjust it
            if (endDate && endDate < date) {
                const newEndDate = new Date(date.getTime() + 60 * 60 * 1000); // Add 1 hour
                setEndDate(newEndDate);
                setData('end_datetime', newEndDate.toISOString());
            }
        } else {
            setData('start_datetime', '');
        }
    };

    const handleEndDateChange = (date) => {
        setEndDate(date);
        if (date) {
            // Format as ISO8601 for Laravel
            setData('end_datetime', date.toISOString());
        } else {
            setData('end_datetime', '');
        }
    };

    // Update recurrence rule when recurrence options change
    useEffect(() => {
        if (showRecurrence) {
            const parts = [`FREQ=${recurrence.freq}`];
            
            if (recurrence.interval > 1) {
                parts.push(`INTERVAL=${recurrence.interval}`);
            }
            
            if (recurrence.count) {
                parts.push(`COUNT=${recurrence.count}`);
            } else if (recurrence.until) {
                // Convert date format (YYYY-MM-DD) to UNTIL format (YYYYMMDD)
                const until = recurrence.until.replace(/-/g, '') + 'T000000Z';
                parts.push(`UNTIL=${until}`);
            }
            
            if (recurrence.byDay.length > 0) {
                parts.push(`BYDAY=${recurrence.byDay.join(',')}`);
            }
            
            setData('recurrence_rule', parts.join(';'));
        } else {
            setData('recurrence_rule', '');
        }
    }, [recurrence, showRecurrence]);

    const weekdays = [
        { value: 'MO', label: 'Mon' },
        { value: 'TU', label: 'Tue' },
        { value: 'WE', label: 'Wed' },
        { value: 'TH', label: 'Thu' },
        { value: 'FR', label: 'Fri' },
        { value: 'SA', label: 'Sat' },
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

    return (
        <>
            <Head title={`Edit ${event.title}`} />
            <Layout auth={auth}>
                <div className="max-w-3xl mx-auto">
                <div>
                    <div className="bg-white">
                        <div className="bg-secondary px-6 py-4">
                            <h1 className="text-2xl font-semibold text-white">Edit Event</h1>
                        </div>
                    </div>
                </div>

                <div className="bg-white p-6" style={{ boxShadow: 'var(--shadow-card)' }}>
                    <form onSubmit={handleSubmit} className="space-y-6">
                        <div>
                            <label htmlFor="calendar_id" className="block text-sm font-medium text-gray-700 mb-1">
                                Calendar *
                            </label>
                            <Select
                                id="calendar_id"
                                value={data.calendar_id}
                                onChange={(e) => setData('calendar_id', e.target.value)}
                                error={errors.calendar_id}
                                required
                            >
                                {calendars.map((calendar) => (
                                    <option key={calendar.id} value={calendar.id}>
                                        {calendar.name}
                                    </option>
                                ))}
                            </Select>
                        </div>

                        <div>
                            <label htmlFor="title" className="block text-sm font-medium text-gray-700 mb-1">
                                Event Title *
                            </label>
                            <Input
                                id="title"
                                value={data.title}
                                onChange={(e) => setData('title', e.target.value)}
                                error={errors.title}
                                required
                            />
                        </div>

                        <div>
                            <label htmlFor="short_description" className="block text-sm font-medium text-gray-700 mb-1">
                                Short Description * (for Discord)
                            </label>
                            <MarkdownEditor
                                value={data.short_description}
                                onChange={(value) => setData('short_description', value)}
                                error={errors.short_description}
                                placeholder="Enter short description for Discord notifications (markdown supported)..."
                            />
                            <p className="mt-1 text-xs text-gray-500">
                                This description will appear in Discord notifications. Markdown formatting is supported.
                            </p>
                        </div>

                        <div>
                            <label htmlFor="long_description" className="block text-sm font-medium text-gray-700 mb-1">
                                Long Description *
                            </label>
                            <MarkdownEditor
                                value={data.long_description}
                                onChange={(value) => setData('long_description', value)}
                                error={errors.long_description}
                                placeholder="Enter detailed event description (markdown supported)..."
                            />
                        </div>

                        <div>
                            <label htmlFor="featured_airports" className="block text-sm font-medium text-gray-700 mb-1">
                                Featured Airports/Facilities
                            </label>
                            <AirportSelector
                                value={data.featured_airports}
                                onChange={(airports) => setData('featured_airports', airports)}
                                error={errors.featured_airports}
                            />
                            <p className="mt-1 text-xs text-gray-500">
                                Add ICAO codes for airports or facilities featured in this event
                            </p>
                        </div>

                        <div>
                            <label htmlFor="discord_staffing_channel_id" className="block text-sm font-medium text-gray-700 mb-1">
                                Discord Staffing Channel
                            </label>
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
                                                <option key={channel.id} value={channel.id}>
                                                    #{channel.name}
                                                </option>
                                            ))}
                                        </optgroup>
                                    ))
                                )}
                            </Select>
                            <p className="mt-1 text-xs text-gray-500">
                                Select a Discord channel for staffing messages (optional)
                            </p>
                        </div>

                        <div className="grid grid-cols-2 gap-4">
                            <div>
                                <label htmlFor="start_datetime" className="block text-sm font-medium text-gray-700 dark:text-dark-text mb-1">
                                    Start Date & Time (UTC) *
                                </label>
                                <DateTimePicker
                                    selected={startDate}
                                    onChange={handleStartDateChange}
                                    error={errors.start_datetime}
                                    required
                                />
                            </div>
                            <div>
                                <label htmlFor="end_datetime" className="block text-sm font-medium text-gray-700 dark:text-dark-text mb-1">
                                    End Date & Time (UTC) *
                                </label>
                                <DateTimePicker
                                    selected={endDate}
                                    onChange={handleEndDateChange}
                                    minDate={startDate}
                                    error={errors.end_datetime}
                                    required
                                />
                            </div>
                        </div>

                        <div>
                            <label htmlFor="banner" className="block text-sm font-medium text-gray-700 mb-1">
                                Banner Image (16:9 ratio)
                            </label>
                            {previewUrl && (
                                <div className="mb-3 relative w-full border-2 border-grey-200" style={{ aspectRatio: '16/9' }}>
                                    <img
                                        src={previewUrl}
                                        alt="Banner preview"
                                        className="w-full h-full object-contain"
                                    />
                                </div>
                            )}
                            <input
                                id="banner"
                                type="file"
                                accept="image/*"
                                onChange={handleBannerChange}
                                className="block w-full text-sm text-gray-700 file:mr-4 file:py-2 file:px-4 file:border-2 file:border-secondary file:text-sm file:font-semibold file:bg-secondary file:text-white hover:file:bg-secondary-600"
                            />
                            {errors.banner && (
                                <p className="mt-1 text-sm text-danger">{errors.banner}</p>
                            )}
                            <p className="mt-1 text-xs text-gray-500">
                                Leave empty to keep the current banner
                            </p>
                        </div>

                        <div>
                            <label className="flex items-center mb-2">
                                <input
                                    type="checkbox"
                                    checked={showRecurrence}
                                    onChange={(e) => {
                                        setShowRecurrence(e.target.checked);
                                        if (!e.target.checked) {
                                            setData('recurrence_rule', '');
                                        }
                                    }}
                                    className="border-2 border-grey-300 text-secondary focus:border-secondary"
                                />
                                <span className="ml-2 text-sm font-medium text-gray-700">
                                    Recurring Event
                                </span>
                            </label>
                            {showRecurrence && (
                                <div className="space-y-4 p-4 bg-grey-50 border-2 border-grey-200">
                                    <div className="grid grid-cols-2 gap-4">
                                        <div>
                                            <label className="block text-sm font-medium text-gray-700 mb-1">
                                                Frequency
                                            </label>
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
                                        <div>
                                            <label className="block text-sm font-medium text-gray-700 mb-1">
                                                Repeat Every
                                            </label>
                                            <Input
                                                type="number"
                                                min="1"
                                                value={recurrence.interval}
                                                onChange={(e) => setRecurrence({ ...recurrence, interval: parseInt(e.target.value) || 1 })}
                                            />
                                        </div>
                                    </div>

                                    {recurrence.freq === 'WEEKLY' && (
                                        <div>
                                            <label className="block text-sm font-medium text-gray-700 mb-2">
                                                Repeat On
                                            </label>
                                            <div className="flex gap-2">
                                                {weekdays.map(day => (
                                                    <button
                                                        key={day.value}
                                                        type="button"
                                                        onClick={() => toggleWeekday(day.value)}
                                                        className={`px-3 py-2 text-sm font-medium border-2 transition-colors ${
                                                            recurrence.byDay.includes(day.value)
                                                                ? 'bg-secondary text-white border-secondary'
                                                                : 'bg-white text-gray-700 border-grey-300 hover:border-secondary'
                                                        }`}
                                                    >
                                                        {day.label}
                                                    </button>
                                                ))}
                                            </div>
                                        </div>
                                    )}

                                    <div>
                                        <label className="block text-sm font-medium text-gray-700 mb-2">
                                            End Condition
                                        </label>
                                        <div className="space-y-3">
                                            <label className="flex items-center gap-2">
                                                <input
                                                    type="radio"
                                                    name="endCondition"
                                                    checked={!!recurrence.count}
                                                    onChange={() => setRecurrence({ ...recurrence, count: '10', until: '' })}
                                                    className="border-2 border-grey-300 text-secondary focus:border-secondary"
                                                />
                                                <span className="text-sm text-gray-700">After</span>
                                                <Input
                                                    type="number"
                                                    min="1"
                                                    value={recurrence.count}
                                                    onChange={(e) => setRecurrence({ ...recurrence, count: e.target.value, until: '' })}
                                                    className="w-20"
                                                    disabled={!recurrence.count}
                                                />
                                                <span className="text-sm text-gray-700">occurrences</span>
                                            </label>
                                            <label className="flex items-center gap-2">
                                                <input
                                                    type="radio"
                                                    name="endCondition"
                                                    checked={!!recurrence.until}
                                                    onChange={() => setRecurrence({ ...recurrence, count: '', until: new Date().toISOString().split('T')[0] })}
                                                    className="border-2 border-grey-300 text-secondary focus:border-secondary"
                                                />
                                                <span className="text-sm text-gray-700">On date</span>
                                                <Input
                                                    type="date"
                                                    value={recurrence.until}
                                                    onChange={(e) => setRecurrence({ ...recurrence, count: '', until: e.target.value })}
                                                    disabled={!recurrence.until}
                                                />
                                            </label>
                                        </div>
                                    </div>

                                    {event.recurrence_rule && (
                                        <p className="text-xs text-gray-500 pt-2 border-t-2 border-grey-200">
                                            Note: Changing recurrence rules only affects future instances
                                        </p>
                                    )}
                                </div>
                            )}
                        </div>

                        <div className="flex justify-end space-x-3">
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
                </div>
                </div>
        </Layout>
        </>
    );
}
