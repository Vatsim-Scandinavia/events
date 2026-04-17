import { useForm, usePage, Head } from '@inertiajs/react';
import Layout from '../../Layouts/Layout';
import Button from '../../Components/Button';
import Input from '../../Components/Input';
import Card from '../../Components/Card';
import TimeInput from '../../Components/TimeInput';
import { PlusIcon, TrashIcon, ChevronUpIcon, ChevronDownIcon } from '@heroicons/react/24/solid';

const labelClass = 'block text-sm font-medium text-neutral-700 dark:text-neutral-300 mb-1';

function emptyPosition() {
    return { position_id: '', position_name: '', start_time: '', end_time: '', is_local_booking: false };
}

function emptySection() {
    return { title: '', positions: [emptyPosition()] };
}

export default function ManageStaffing({ event, ccPositions = [] }) {
    const { auth } = usePage().props;
    const evt = event.data ?? event;

    const initialSections = evt.staffing?.sections?.length
        ? evt.staffing.sections.map((s) => ({
            title: s.title,
            positions: s.positions.map((p) => ({
                position_id:      p.position_id,
                position_name:    p.position_name,
                start_time:       p.start_time ?? '',
                end_time:         p.end_time ?? '',
                is_local_booking: p.is_local_booking ?? false,
            })),
        }))
        : [emptySection()];

    const { data, setData, put, processing, errors } = useForm({ sections: initialSections });

    const addSection = () => setData('sections', [...data.sections, emptySection()]);

    const removeSection = (si) =>
        setData('sections', data.sections.filter((_, i) => i !== si));

    const moveSection = (si, dir) => {
        const sections = [...data.sections];
        const target = si + dir;
        if (target < 0 || target >= sections.length) return;
        [sections[si], sections[target]] = [sections[target], sections[si]];
        setData('sections', sections);
    };

    const updateSection = (si, field, value) => {
        const sections = data.sections.map((s, i) => (i === si ? { ...s, [field]: value } : s));
        setData('sections', sections);
    };

    const addPosition = (si) => {
        const sections = data.sections.map((s, i) =>
            i === si ? { ...s, positions: [...s.positions, emptyPosition()] } : s
        );
        setData('sections', sections);
    };

    const removePosition = (si, pi) => {
        const sections = data.sections.map((s, i) =>
            i === si ? { ...s, positions: s.positions.filter((_, j) => j !== pi) } : s
        );
        setData('sections', sections);
    };

    const movePosition = (si, pi, dir) => {
        const sections = data.sections.map((s, i) => {
            if (i !== si) return s;
            const positions = [...s.positions];
            const target = pi + dir;
            if (target < 0 || target >= positions.length) return s;
            [positions[pi], positions[target]] = [positions[target], positions[pi]];
            return { ...s, positions };
        });
        setData('sections', sections);
    };

    const updatePosition = (si, pi, field, value) => {
        const sections = data.sections.map((s, i) =>
            i === si
                ? {
                    ...s,
                    positions: s.positions.map((p, j) =>
                        j === pi ? { ...p, [field]: value } : p
                    ),
                }
                : s
        );
        setData('sections', sections);
    };

    const ccCallsigns = new Set(ccPositions.map((p) => p.callsign.toUpperCase()));

    const isInvalidCcPosition = (pos) =>
        !pos.is_local_booking &&
        ccPositions.length > 0 &&
        pos.position_id.trim() !== '' &&
        !ccCallsigns.has(pos.position_id.trim().toUpperCase());

    const submit = (e) => {
        e.preventDefault();
        put(`/events/${evt.slug}/staffing`);
    };

    return (
        <>
            <Head title={`Staffing — ${evt.title}`} />
            <Layout auth={auth}>
                <div className="flex items-center justify-between gap-4">
                    <h1 className="text-3xl font-bold text-neutral-900 dark:text-neutral-100">
                        Manage Staffing
                    </h1>
                    <span className="text-neutral-500 dark:text-neutral-400">{evt.title}</span>
                </div>

                <form onSubmit={submit} className="flex flex-col gap-6">
                    {data.sections.map((section, si) => (
                        <Card
                            key={si}
                            title={`Section ${si + 1}`}
                            actions={
                                <div className="flex items-center gap-2">
                                    <div className="flex">
                                        <button
                                            type="button"
                                            onClick={() => moveSection(si, -1)}
                                            disabled={si === 0}
                                            className="p-1 text-neutral-300 hover:text-white disabled:opacity-30 disabled:cursor-not-allowed"
                                            title="Move section up"
                                        >
                                            <ChevronUpIcon className="w-4 h-4" />
                                        </button>
                                        <button
                                            type="button"
                                            onClick={() => moveSection(si, 1)}
                                            disabled={si === data.sections.length - 1}
                                            className="p-1 text-neutral-300 hover:text-white disabled:opacity-30 disabled:cursor-not-allowed"
                                            title="Move section down"
                                        >
                                            <ChevronDownIcon className="w-4 h-4" />
                                        </button>
                                    </div>
                                    {data.sections.length > 1 && (
                                        <Button variant="outline-danger" size="sm" onClick={() => removeSection(si)}>
                                            <TrashIcon className="w-3 h-3 mr-1" /> Remove section
                                        </Button>
                                    )}
                                </div>
                            }
                        >
                            <div className="bg-white dark:bg-neutral-800 p-6 flex flex-col gap-6">
                                {/* Section title */}
                                <div>
                                    <label className={labelClass}>Section title</label>
                                    <Input
                                        value={section.title}
                                        onChange={(e) => updateSection(si, 'title', e.target.value)}
                                        placeholder="e.g. Enroute, Approach"
                                        error={errors[`sections.${si}.title`]}
                                    />
                                </div>

                                {/* Positions table */}
                                <div className="flex flex-col gap-2">
                                    <label className={labelClass}>Positions</label>

                                    <div className="border border-neutral-200 dark:border-neutral-700 divide-y divide-neutral-200 dark:divide-neutral-700">
                                        {/* Header */}
                                        <div className="grid grid-cols-[2rem_1fr_2fr_6rem_6rem_5rem_2.5rem] gap-2 px-4 py-2 bg-neutral-50 dark:bg-neutral-700/40 text-xs font-medium text-neutral-500 dark:text-neutral-400 uppercase tracking-wide">
                                            <span />
                                            <span>Callsign</span>
                                            <span>Name</span>
                                            <span>Start</span>
                                            <span>End</span>
                                            <span>Local only</span>
                                            <span />
                                        </div>

                                        {section.positions.map((pos, pi) => (
                                            <div
                                                key={pi}
                                                className="grid grid-cols-[2rem_1fr_2fr_6rem_6rem_5rem_2.5rem] gap-2 items-center px-4 py-2"
                                            >
                                                {/* Up/down reorder */}
                                                <div className="flex flex-col items-center">
                                                    <button
                                                        type="button"
                                                        onClick={() => movePosition(si, pi, -1)}
                                                        disabled={pi === 0}
                                                        className="text-neutral-400 hover:text-neutral-700 dark:hover:text-neutral-200 disabled:opacity-20 disabled:cursor-not-allowed"
                                                        title="Move up"
                                                    >
                                                        <ChevronUpIcon className="w-3.5 h-3.5" />
                                                    </button>
                                                    <button
                                                        type="button"
                                                        onClick={() => movePosition(si, pi, 1)}
                                                        disabled={pi === section.positions.length - 1}
                                                        className="text-neutral-400 hover:text-neutral-700 dark:hover:text-neutral-200 disabled:opacity-20 disabled:cursor-not-allowed"
                                                        title="Move down"
                                                    >
                                                        <ChevronDownIcon className="w-3.5 h-3.5" />
                                                    </button>
                                                </div>
                                                {pos.is_local_booking ? (
                                                    <Input
                                                        value={pos.position_id}
                                                        onChange={(e) => updatePosition(si, pi, 'position_id', e.target.value.toUpperCase())}
                                                        placeholder="LOCAL_POS"
                                                        error={errors[`sections.${si}.positions.${pi}.position_id`]}
                                                    />
                                                ) : (
                                                    <>
                                                        <Input
                                                            list={`cc-pos-${si}-${pi}`}
                                                            value={pos.position_id}
                                                            onChange={(e) => {
                                                                const val = e.target.value.toUpperCase();
                                                                const match = ccPositions.find((p) => p.callsign === val);
                                                                const sections = data.sections.map((s, i) =>
                                                                    i === si ? {
                                                                        ...s,
                                                                        positions: s.positions.map((p, j) =>
                                                                            j === pi
                                                                                ? { ...p, position_id: val, ...(match ? { position_name: match.name } : {}) }
                                                                                : p
                                                                        ),
                                                                    } : s
                                                                );
                                                                setData('sections', sections);
                                                            }}
                                                            placeholder="ESSA_APP"
                                                            error={errors[`sections.${si}.positions.${pi}.position_id`] || (isInvalidCcPosition(pos) ? 'Not a known CC position' : undefined)}
                                                        />
                                                        <datalist id={`cc-pos-${si}-${pi}`}>
                                                            {ccPositions.map((p) => (
                                                                <option key={p.callsign} value={p.callsign}>{p.name}</option>
                                                            ))}
                                                        </datalist>
                                                    </>
                                                )}
                                                <Input
                                                    value={pos.position_name}
                                                    onChange={(e) => updatePosition(si, pi, 'position_name', e.target.value)}
                                                    placeholder={pos.is_local_booking ? 'Local Position' : 'Stockholm Approach'}
                                                    disabled={!pos.is_local_booking}
                                                    className={!pos.is_local_booking ? 'opacity-60 cursor-not-allowed' : ''}
                                                    error={errors[`sections.${si}.positions.${pi}.position_name`]}
                                                />
                                                <TimeInput
                                                    value={pos.start_time}
                                                    onChange={(v) => updatePosition(si, pi, 'start_time', v)}
                                                />
                                                <TimeInput
                                                    value={pos.end_time}
                                                    onChange={(v) => updatePosition(si, pi, 'end_time', v)}
                                                />
                                                <div className="flex justify-center">
                                                    <input
                                                        type="checkbox"
                                                        checked={pos.is_local_booking}
                                                        onChange={(e) => updatePosition(si, pi, 'is_local_booking', e.target.checked)}
                                                        className="w-4 h-4 accent-primary"
                                                    />
                                                </div>
                                                <button
                                                    type="button"
                                                    onClick={() => removePosition(si, pi)}
                                                    disabled={section.positions.length === 1}
                                                    className="flex justify-center text-danger disabled:opacity-30 disabled:cursor-not-allowed"
                                                >
                                                    <TrashIcon className="w-4 h-4" />
                                                </button>
                                            </div>
                                        ))}
                                    </div>

                                    <Button
                                        type="button"
                                        variant="outline-secondary"
                                        size="sm"
                                        onClick={() => addPosition(si)}
                                        className="self-start"
                                    >
                                        <PlusIcon className="w-3 h-3 mr-1" /> Add position
                                    </Button>
                                </div>
                            </div>
                        </Card>
                    ))}

                    <div className="flex items-center justify-between gap-4">
                        <Button type="button" variant="outline-secondary" onClick={addSection}>
                            <PlusIcon className="w-4 h-4 mr-1" /> Add section
                        </Button>

                        <div className="flex gap-3">
                            <a href={`/events/${evt.slug}`}>
                                <Button type="button" variant="outline-secondary">Cancel</Button>
                            </a>
                            <Button type="submit" variant="primary" disabled={processing}>
                                {processing ? 'Saving…' : 'Save staffing'}
                            </Button>
                        </div>
                    </div>
                </form>
            </Layout>
        </>
    );
}
