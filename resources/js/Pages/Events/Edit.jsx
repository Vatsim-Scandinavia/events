import { useForm, usePage, Head } from '@inertiajs/react';
import Layout from '../../Layouts/Layout';
import Button from '../../Components/Button';
import Input from '../../Components/Input';
import Select from '../../Components/Select';
import MarkdownEditor from '../../Components/MarkdownEditor';
import { useState, useEffect } from 'react';
import Flatpickr from "react-flatpickr";
import "flatpickr/dist/flatpickr.min.css"; 

export default function Edit({ event, calendars, bannerUrl }) {
    const { auth, discordChannels } = usePage().props;
    const [showRecurrence, setShowRecurrence] = useState(!!event.recurrence_rule);

    const stripTimezone = (dateStr) => {
        if (!dateStr) return '';
        return dateStr.replace('Z', '').split('+')[0].replace('T', ' ');
    };

    const { data, setData, post, processing, errors } = useForm({
        _method: 'PUT',
        calendar_id: event.calendar_id || '',
        title: event.title || '',
        short_description: event.short_description || '',
        long_description: event.long_description || '',
        featured_airports: event.featured_airports || [],
        start_datetime: stripTimezone(event.start_datetime),
        end_datetime: stripTimezone(event.end_datetime),
        banner: null,
        recurrence_rule: event.recurrence_rule || '',
        discord_staffing_channel_id: event.discord_staffing_channel_id || '',
    });

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
                parsed.until = value.substring(0, 8).replace(/(\d{4})(\d{2})(\d{2})/, '$1-$2-$3');
            }
            if (key === 'BYDAY') parsed.byDay = value.split(',');
        });
        return parsed;
    };
    
    const [recurrence, setRecurrence] = useState(parseRRule(event.recurrence_rule));

    const handleSubmit = (e) => {
        e.preventDefault();
        
        const payload = {
            ...data,
            start_datetime: data.start_datetime ? `${data.start_datetime.replace(' ', 'T')}:00Z` : '',
            end_datetime: data.end_datetime ? `${data.end_datetime.replace(' ', 'T')}:00Z` : '',
        };

        post(`/events/${event.id}`, { 
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

    const fpInputClass = "w-full border-2 border-grey-300 p-2 focus:border-secondary focus:ring-0 outline-none transition-colors";

    return (
        <Layout auth={auth}>
            <Head title={`Edit ${event.title}`} />
            <div className="max-w-3xl mx-auto">
                <div className="bg-secondary px-6 py-4">
                    <h1 className="text-2xl font-semibold text-white">Edit Event</h1>
                </div>

                <div className="bg-white p-6" style={{ boxShadow: 'var(--shadow-card)' }}>
                    <form onSubmit={handleSubmit} className="space-y-6">
                        <div>
                            <label className="block text-sm font-medium text-gray-700 mb-1">Calendar *</label>
                            <Select value={data.calendar_id} onChange={(e) => setData('calendar_id', e.target.value)} error={errors.calendar_id} required>
                                <option value="">Select a calendar</option>
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

                        {/* Discord Channel Moved Above Dates */}
                        <div>
                            <label className="block text-sm font-medium text-gray-700 mb-1">Staffing Discord Channel</label>
                            <Select value={data.discord_staffing_channel_id} onChange={(e) => setData('discord_staffing_channel_id', e.target.value)} error={errors.discord_staffing_channel_id}>
                                <option value="">No Discord Channel</option>
                                {discordChannels?.map((channel) => (
                                    <option key={channel.id} value={channel.id}>#{channel.name}</option>
                                ))}
                            </Select>
                        </div>

                        <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div>
                                <label className="block text-sm font-medium text-gray-700 mb-1">Start Date & Time (UTC) *</label>
                                <Flatpickr
                                    value={data.start_datetime}
                                    onChange={(dates, dateStr) => setData('start_datetime', dateStr)}
                                    options={{ enableTime: true, dateFormat: "Y-m-d H:i", time_24hr: true }}
                                    className={fpInputClass}
                                />
                                {errors.start_datetime && <p className="mt-1 text-sm text-danger">{errors.start_datetime}</p>}
                            </div>

                            <div>
                                <label className="block text-sm font-medium text-gray-700 mb-1">End Date & Time (UTC) *</label>
                                <Flatpickr
                                    value={data.end_datetime}
                                    onChange={(dates, dateStr) => setData('end_datetime', dateStr)}
                                    options={{ enableTime: true, dateFormat: "Y-m-d H:i", time_24hr: true, minDate: data.start_datetime }}
                                    className={fpInputClass}
                                />
                                {errors.end_datetime && <p className="mt-1 text-sm text-danger">{errors.end_datetime}</p>}
                            </div>
                        </div>

                        <div>
                            <label className="block text-sm font-medium text-gray-700 mb-1">Event Banner</label>
                            {bannerUrl && (
                                <div className="mb-2">
                                    <img src={bannerUrl} alt="Current Banner" className="h-32 w-full object-cover border-2 border-grey-200" />
                                </div>
                            )}
                            <input 
                                type="file" 
                                onChange={(e) => setData('banner', e.target.files[0])} 
                                className="w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:border-0 file:text-sm file:font-semibold file:bg-secondary file:text-white hover:file:bg-secondary-dark cursor-pointer" 
                            />
                            {errors.banner && <p className="mt-1 text-sm text-danger">{errors.banner}</p>}
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
                            <Button type="submit" variant="success" disabled={processing}>Update Event</Button>
                        </div>
                    </form>
                </div>
            </div>
        </Layout>
    );
}