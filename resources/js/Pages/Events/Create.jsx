import { useForm, usePage, Head } from '@inertiajs/react';
import Layout from '../../Layouts/Layout';
import Button from '../../Components/Button';
import Input from '../../Components/Input';
import Select from '../../Components/Select';
import AirportSelector from '../../Components/AirportSelector';
import MarkdownEditor from '../../Components/MarkdownEditor';
import Flatpickr from "react-flatpickr";
import "flatpickr/dist/flatpickr.min.css"; 
import { useState, useEffect } from 'react';

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

    const formatToWallTime = (date) => {
        const pad = (num) => String(num).padStart(2, '0');
        return `${date.getFullYear()}-${pad(date.getMonth() + 1)}-${pad(date.getDate())} ${pad(date.getHours())}:${pad(date.getMinutes())}`;
    };

    const handleStartDateChange = (selectedDates, dateStr) => {
        setData(prev => {
            const newData = { ...prev, start_datetime: dateStr };
            
            if (selectedDates[0]) {
                const start = selectedDates[0];
                const currentEnd = prev.end_datetime ? new Date(prev.end_datetime.replace(' ', 'T')) : null;
                
                if (!prev.end_datetime || (currentEnd && currentEnd <= start)) {
                    const oneHourLater = new Date(start.getTime() + 60 * 60 * 1000);
                    newData.end_datetime = formatToWallTime(oneHourLater);
                }
            }
            return newData;
        });
    };

    const handleSubmit = (e) => {
        e.preventDefault();
        
        const payload = {
            ...data,
            start_datetime: data.start_datetime ? `${data.start_datetime.replace(' ', 'T')}:00Z` : '',
            end_datetime: data.end_datetime ? `${data.end_datetime.replace(' ', 'T')}:00Z` : '',
        };

        post('/events', {
            forceFormData: true,
            data: payload
        });
    };

    useEffect(() => {
        if (showRecurrence) {
            const parts = [`FREQ=${recurrence.freq}`];
            if (recurrence.interval > 1) parts.push(`INTERVAL=${recurrence.interval}`);
            if (recurrence.count) {
                parts.push(`COUNT=${recurrence.count}`);
            } else if (recurrence.until) {
                const until = recurrence.until.replace(/-/g, '') + 'T000000Z';
                parts.push(`UNTIL=${until}`);
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

    const fpInputClass = `w-full px-3 py-2 border-2 rounded-none transition-colors focus:outline-none focus:ring-0 focus:border-secondary`;

    return (
        <Layout auth={auth}>
            <Head title="Create Event" />
            <div className="max-w-3xl mx-auto">
                <div className="bg-secondary px-6 py-4">
                    <h1 className="text-2xl font-semibold text-white">Create Event</h1>
                </div>

                <div className="bg-white p-6" style={{ boxShadow: 'var(--shadow-card)' }}>
                    <form onSubmit={handleSubmit} className="space-y-6">
                        <div>
                            <label className="block text-sm font-medium text-gray-700 mb-1">Calendar *</label>
                            <Select value={data.calendar_id} onChange={(e) => setData('calendar_id', e.target.value)} error={errors.calendar_id} required>
                                {calendars.map((c) => <option key={c.id} value={c.id}>{c.name}</option>)}
                            </Select>
                        </div>

                        <div>
                            <label className="block text-sm font-medium text-gray-700 mb-1">Event Title *</label>
                            <Input value={data.title} onChange={(e) => setData('title', e.target.value)} error={errors.title} required />
                        </div>

                        <div>
                            <label className="block text-sm font-medium text-gray-700 mb-1">Short Description *</label>
                            <MarkdownEditor value={data.short_description} onChange={(v) => setData('short_description', v)} error={errors.short_description} />
                        </div>

                        <div>
                            <label className="block text-sm font-medium text-gray-700 mb-1">Long Description *</label>
                            <MarkdownEditor value={data.long_description} onChange={(v) => setData('long_description', v)} error={errors.long_description} />
                        </div>

                        <div>
                            <label className="block text-sm font-medium text-gray-700 mb-1">Featured Airports/Facilities</label>
                            <AirportSelector value={data.featured_airports} onChange={(a) => setData('featured_airports', a)} error={errors.featured_airports} />
                        </div>

                        <div>
                            <label className="block text-sm font-medium text-gray-700 mb-1">Discord Staffing Channel</label>
                            <Select value={data.discord_staffing_channel_id} onChange={(e) => setData('discord_staffing_channel_id', e.target.value)} error={errors.discord_staffing_channel_id}>
                                <option value="">No Discord channel</option>
                                {!loadingChannels && discordChannels.map((guild) => (
                                    <optgroup key={guild.guild_id} label={guild.guild_name}>
                                        {guild.channels.map((c) => <option key={c.id} value={c.id}>#{c.name}</option>)}
                                    </optgroup>
                                ))}
                            </Select>
                        </div>

                        <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div>
                                <label className="block text-sm font-medium text-gray-700 mb-1">Start Date & Time (UTC) *</label>
                                <Flatpickr
                                    value={data.start_datetime}
                                    onChange={handleStartDateChange}
                                    options={{ enableTime: true, dateFormat: "Y-m-d H:i", time_24hr: true }}
                                    className={`${fpInputClass} ${errors.start_datetime ? 'border-danger' : 'border-grey-300'}`}
                                    placeholder="Select start date..."
                                />
                                {errors.start_datetime && <p className="mt-1 text-sm text-danger">{errors.start_datetime}</p>}
                            </div>

                            <div>
                                <label className="block text-sm font-medium text-gray-700 mb-1">End Date & Time (UTC) *</label>
                                <Flatpickr
                                    value={data.end_datetime}
                                    onChange={(dates, dateStr) => setData('end_datetime', dateStr)}
                                    options={{ enableTime: true, dateFormat: "Y-m-d H:i", time_24hr: true, minDate: data.start_datetime }}
                                    className={`${fpInputClass} ${errors.end_datetime ? 'border-danger' : 'border-grey-300'}`}
                                    placeholder="Select end date..."
                                />
                                {errors.end_datetime && <p className="mt-1 text-sm text-danger">{errors.end_datetime}</p>}
                            </div>
                        </div>

                        <div>
                            <label className="block text-sm font-medium text-gray-700 mb-1">Banner Image</label>
                            <input type="file" accept="image/*" onChange={(e) => setData('banner', e.target.files[0])} className="block w-full text-sm text-gray-700 file:mr-4 file:py-2 file:px-4 file:border-2 file:border-secondary file:bg-secondary file:text-white" />
                        </div>

                        <div className="pt-4 border-t border-grey-100">
                            <label className="flex items-center mb-4 cursor-pointer">
                                <input type="checkbox" checked={showRecurrence} onChange={(e) => setShowRecurrence(e.target.checked)} className="mr-2" />
                                <span className="text-sm font-medium text-gray-700">Recurring Event</span>
                            </label>

                            {showRecurrence && (
                                <div className="space-y-4 p-4 bg-grey-50 border-2 border-grey-200">
                                    <div className="grid grid-cols-2 gap-4">
                                        <Select value={recurrence.freq} onChange={(e) => setRecurrence({ ...recurrence, freq: e.target.value })}>
                                            <option value="DAILY">Daily</option>
                                            <option value="WEEKLY">Weekly</option>
                                            <option value="MONTHLY">Monthly</option>
                                        </Select>
                                        <Input type="number" min="1" value={recurrence.interval} onChange={(e) => setRecurrence({ ...recurrence, interval: parseInt(e.target.value) || 1 })} />
                                    </div>
                                    <div className="flex gap-2">
                                        {weekdays.map(day => (
                                            <button key={day.value} type="button" onClick={() => toggleWeekday(day.value)} className={`px-3 py-2 text-sm font-medium border-2 ${recurrence.byDay.includes(day.value) ? 'bg-secondary text-white border-secondary' : 'bg-white text-gray-700 border-grey-300'}`}>{day.label}</button>
                                        ))}
                                    </div>
                                    <div className="space-y-3">
                                        <label className="flex items-center gap-2">
                                            <input type="radio" checked={!!recurrence.count} onChange={() => setRecurrence({ ...recurrence, count: '10', until: '' })} /> After <Input type="number" className="w-20" value={recurrence.count} disabled={!recurrence.count} onChange={(e) => setRecurrence({ ...recurrence, count: e.target.value })} /> occurrences
                                        </label>
                                        <label className="flex items-center gap-2">
                                            <input type="radio" checked={!!recurrence.until} onChange={() => setRecurrence({ ...recurrence, count: '', until: new Date().toISOString().split('T')[0] })} /> On date <Input type="date" value={recurrence.until} disabled={!recurrence.until} onChange={(e) => setRecurrence({ ...recurrence, count: '', until: e.target.value })} />
                                        </label>
                                    </div>
                                </div>
                            )}
                        </div>

                        <div className="flex justify-end space-x-3 pt-4 border-t-2 border-grey-100">
                            <Button type="button" variant="secondary" onClick={() => window.history.back()}>Cancel</Button>
                            <Button type="submit" variant="success" disabled={processing}>Create Event</Button>
                        </div>
                    </form>
                </div>
            </div>
        </Layout>
    );
}