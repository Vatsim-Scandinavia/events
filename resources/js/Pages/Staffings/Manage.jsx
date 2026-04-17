import { useState, useCallback, useRef, useEffect } from 'react';
import { useForm, usePage, router, Link, Head } from '@inertiajs/react';
import { DndProvider, useDrag, useDrop } from 'react-dnd';
import { HTML5Backend } from 'react-dnd-html5-backend';
import Layout from '../../Layouts/Layout';
import Button from '../../Components/Button';
import Input from '../../Components/Input';
import MarkdownEditor from '../../Components/MarkdownEditor';
import Modal from '../../Components/Modal';
import TimeInput from '../../Components/TimeInput';
import { TimeDisplay } from '../../Components/DateTimeDisplay';
import { TrashIcon, PencilIcon, ArrowPathIcon, UserIcon, PlusIcon } from '@heroicons/react/24/solid';

const POSITION_TYPE = 'POSITION';

const labelClass = "block text-sm font-medium text-neutral-700 dark:text-neutral-300 mb-1";
const sectionClass = "flex flex-col gap-1";

function DraggablePosition({ position, index, movePosition, auth, handleUnbookPosition, handleDeletePosition, handleEditPosition }) {
    const ref = useRef(null);

    const [{ isDragging }, drag] = useDrag({
        type: POSITION_TYPE,
        item: { index, position },
        collect: (monitor) => ({
            isDragging: monitor.isDragging(),
        }),
    });

    const [{ isOver }, drop] = useDrop({
        accept: POSITION_TYPE,
        hover(item, monitor) {
            if (!ref.current) return;
            const dragIndex = item.index;
            const hoverIndex = index;
            if (dragIndex === hoverIndex) return;

            const hoverBoundingRect = ref.current?.getBoundingClientRect();
            const hoverMiddleY = (hoverBoundingRect.bottom - hoverBoundingRect.top) / 2;
            const clientOffset = monitor.getClientOffset();
            const hoverClientY = clientOffset.y - hoverBoundingRect.top;

            if (dragIndex < hoverIndex && hoverClientY < hoverMiddleY) return;
            if (dragIndex > hoverIndex && hoverClientY > hoverMiddleY) return;

            movePosition(dragIndex, hoverIndex);
            item.index = hoverIndex;
        },
        collect: (monitor) => ({
            isOver: monitor.isOver(),
        }),
    });

    drag(drop(ref));

    return (
        <div
            ref={ref}
            className={`flex items-center justify-between px-4 py-3 border transition-colors cursor-move
                ${isOver ? 'border-secondary' : 'border-neutral-200 dark:border-neutral-700'}
                ${isDragging ? 'opacity-50' : ''}
                bg-neutral-50 dark:bg-neutral-700/30 hover:bg-neutral-100 dark:hover:bg-neutral-700/50`}
        >
            {/* Left: drag handle + position info — min-w-0 allows this side to shrink */}
            <div className="flex items-center gap-3 flex-1 min-w-0">
                <span className="text-neutral-400 select-none shrink-0 leading-none">⋮⋮</span>
                <div className="flex-1 min-w-0 flex flex-col gap-0.5">
                    <div className="flex items-center gap-2 min-w-0">
                        <span className="font-mono text-sm font-semibold text-secondary dark:text-primary truncate leading-tight">
                            {position.position_id}
                        </span>
                        {position.is_local && (
                            <span className="shrink-0 px-2 py-0.5 text-[10px] font-bold tracking-wider bg-warning text-white uppercase leading-tight">
                                Local
                            </span>
                        )}
                    </div>
                    <span className="text-sm text-neutral-700 dark:text-neutral-300 truncate leading-tight">
                        {position.position_name}
                    </span>
                    {(position.start_time || position.end_time) && (
                        <div className="text-xs text-neutral-500 dark:text-neutral-400">
                            {position.start_time && <TimeDisplay datetime={position.start_time} />}
                            {position.start_time && position.end_time && <span> – </span>}
                            {position.end_time && <TimeDisplay datetime={position.end_time} />}
                        </div>
                    )}
                </div>
            </div>

            {/* Right: booking status + action buttons — shrink-0 keeps buttons always visible */}
            <div className="flex items-center gap-3 shrink-0 ml-3">
                {(position.booked_by_user_id || position.vatsim_cid) ? (
                    <>
                        <span className="text-sm text-success font-medium hidden sm:inline">
                            {position.booked_by_user_id
                                ? `Booked by ${position.booked_by?.name || 'Unknown User'}`
                                : `Booked by ${position.vatsim_cid} (Discord)`
                            }
                        </span>
                        {(auth.user?.id === position.booked_by_user_id ||
                          auth.user?.permissions?.includes('unbook-any-position')) && (
                            <Button variant="warning" onClick={() => handleUnbookPosition(position.id)}>
                                Unbook
                            </Button>
                        )}
                    </>
                ) : (
                    <span className="text-sm text-neutral-500 dark:text-neutral-400 font-medium hidden sm:inline">Available</span>
                )}
                {auth.user?.permissions?.includes('manage-staffings') && (
                    <>
                        <Button variant="secondary" onClick={() => handleEditPosition(position)}>
                            <PencilIcon className="w-4 h-4" />
                        </Button>
                        <Button variant="danger" onClick={() => handleDeletePosition(position.id)}>
                            <TrashIcon className="w-4 h-4" />
                        </Button>
                    </>
                )}
            </div>
        </div>
    );
}

function StaffingSection({ staffing, auth, handleUnbookPosition, handleDeletePosition, handleEditPosition, handleDeleteSection, setSelectedSection, setShowAddPosition }) {
    const [positions, setPositions] = useState(staffing.positions || []);

    useEffect(() => {
        setPositions(staffing.positions || []);
    }, [staffing.positions]);

    const movePosition = useCallback((dragIndex, hoverIndex) => {
        setPositions((prevPositions) => {
            const newPositions = [...prevPositions];
            const draggedPosition = newPositions[dragIndex];
            newPositions.splice(dragIndex, 1);
            newPositions.splice(hoverIndex, 0, draggedPosition);
            return newPositions;
        });
    }, []);

    const savePositionOrder = useCallback(() => {
        const updatedPositions = positions.map((position, index) => ({
            id: position.id,
            order: index * 10,
            staffing_id: staffing.id,
        }));
        router.post('/positions/reorder', { positions: updatedPositions }, { preserveScroll: true });
    }, [positions, staffing.id]);

    useEffect(() => {
        if (positions.length > 0 && positions !== staffing.positions) {
            const timer = setTimeout(() => savePositionOrder(), 500);
            return () => clearTimeout(timer);
        }
    }, [positions, savePositionOrder, staffing.positions]);

    return (
        <div className="border border-neutral-200 dark:border-neutral-700">

            {/* Section Header */}
            <div className="bg-neutral-100 dark:bg-neutral-700/50 border-b border-neutral-200 dark:border-neutral-700 px-6 py-4">
                <div className="flex justify-between items-center gap-4">
                    <div>
                        <h2 className="font-semibold text-neutral-900 dark:text-neutral-100">{staffing.name}</h2>
                        {staffing.synced_to_vatsim && (
                            <p className="text-xs text-success mt-0.5">
                                ✓ Synced to VATSIM on {new Date(staffing.synced_at).toLocaleString()}
                            </p>
                        )}
                    </div>
                    <div className="flex gap-2 shrink-0">
                        <Button
                            variant="success"
                            onClick={() => {
                                setSelectedSection(staffing.id);
                                setShowAddPosition(true);
                            }}
                        >
                            <PlusIcon className="w-4 h-4 mr-1" />
                            Add Position
                        </Button>
                        <Button variant="danger" onClick={() => handleDeleteSection(staffing.id)}>
                            <TrashIcon className="w-4 h-4" />
                        </Button>
                    </div>
                </div>
            </div>

            {/* Positions */}
            <div className="bg-white dark:bg-neutral-800 p-4">
                {positions.length > 0 ? (
                    <div className="flex flex-col gap-2">
                        {positions.map((position, index) => (
                            <DraggablePosition
                                key={position.id}
                                position={position}
                                index={index}
                                movePosition={movePosition}
                                auth={auth}
                                handleUnbookPosition={handleUnbookPosition}
                                handleDeletePosition={handleDeletePosition}
                                handleEditPosition={handleEditPosition}
                            />
                        ))}
                    </div>
                ) : (
                    <p className="text-center text-neutral-500 dark:text-neutral-400 py-10">No positions added yet.</p>
                )}
            </div>
        </div>
    );
}

export default function Manage({ event, staffings: initialStaffings }) {
    const { auth, errors } = usePage().props;
    const [showAddSection, setShowAddSection] = useState(false);
    const [showAddPosition, setShowAddPosition] = useState(false);
    const [showEditPosition, setShowEditPosition] = useState(false);
    const [selectedSection, setSelectedSection] = useState(null);
    const [editingPosition, setEditingPosition] = useState(null);
    const [isLocalPosition, setIsLocalPosition] = useState(false);
    const [apiPositions, setApiPositions] = useState([]);
    const [loadingPositions, setLoadingPositions] = useState(false);
    const [searchQuery, setSearchQuery] = useState('');
    const [settingUpDiscord, setSettingUpDiscord] = useState(false);
    const [resetting, setResetting] = useState(false);

    const { data: staffingDescData, setData: setStaffingDescData, processing: updatingDesc } = useForm({
        staffing_description: event.staffing_description || '',
    });

    const { data: sectionData, setData: setSectionData, post: postSection, reset: resetSection, processing: sectionProcessing } = useForm({
        name: '',
    });

    const { data: positionData, setData: setPositionData, post: postPosition, reset: resetPosition, processing: positionProcessing } = useForm({
        position_id: '',
        position_name: '',
        is_local: false,
        start_time: '',
        end_time: '',
    });

    const { data: editData, setData: setEditData, put: putPosition, reset: resetEdit, processing: editProcessing } = useForm({
        position_id: '',
        position_name: '',
        start_time: '',
        end_time: '',
    });

    useEffect(() => {
        if (showAddPosition && !isLocalPosition && apiPositions.length === 0) {
            setLoadingPositions(true);
            fetch('/api/positions')
                .then(res => res.json())
                .then(data => {
                    setApiPositions(data);
                    setLoadingPositions(false);
                })
                .catch(error => {
                    console.error('Failed to fetch positions:', error);
                    setLoadingPositions(false);
                });
        }
    }, [showAddPosition, isLocalPosition, apiPositions.length]);

    const filteredPositions = apiPositions.filter(position => {
        const query = searchQuery.toLowerCase();
        return position.callsign?.toLowerCase().includes(query) ||
               position.name?.toLowerCase().includes(query);
    });

    const handleSetupDiscord = async () => {
        if (!event.discord_staffing_channel_id) {
            alert('Please configure a Discord channel for this event first.\n\nGo to: Edit Event → Discord Staffing Channel');
            return;
        }
        if (initialStaffings.length === 0) {
            alert('Please add at least one staffing section first.');
            return;
        }
        if (!confirm('This will post a staffing message to Discord. Continue?')) return;

        setSettingUpDiscord(true);
        try {
            const response = await fetch('/api/staffing/setup', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                },
                body: JSON.stringify({ id: initialStaffings[0].id }),
            });
            const data = await response.json();
            if (response.ok) {
                alert('✅ Staffing setup initiated! Check your Discord channel.');
            } else {
                alert('❌ Error: ' + (data.error || data.message || 'Failed to setup Discord staffing'));
            }
        } catch (error) {
            console.error('Failed to setup Discord:', error);
            alert('❌ Failed to connect to Discord bot.');
        } finally {
            setSettingUpDiscord(false);
        }
    };

    const handleResetStaffing = () => {
        if (!confirm('⚠️ This will clear ALL bookings for this event.\n\nAll positions will be reset to available.\nControl Center bookings will be deleted.\nDiscord message will be updated.\n\nContinue?')) return;

        setResetting(true);
        router.post(`/events/${event.id}/staffings/reset`, {}, {
            preserveScroll: true,
            onSuccess: () => setResetting(false),
            onError: () => {
                setResetting(false);
                alert('❌ Failed to reset staffing. Please try again.');
            }
        });
    };

    const handleAddSection = (e) => {
        e.preventDefault();
        postSection(`/events/${event.id}/staffings`, {
            onSuccess: () => {
                setShowAddSection(false);
                resetSection();
                router.reload({ only: ['staffings'] });
            },
        });
    };

    const handleAddPosition = (e) => {
        e.preventDefault();
        if (selectedSection) {
            postPosition(`/staffings/${selectedSection}/positions`, {
                onSuccess: () => {
                    setShowAddPosition(false);
                    resetPosition();
                    setSelectedSection(null);
                    setIsLocalPosition(false);
                    setSearchQuery('');
                    router.reload({ only: ['staffings'] });
                },
            });
        }
    };

    const handlePositionSelect = (position) => {
        setPositionData({ position_id: position.callsign, position_name: position.name, is_local: false });
    };

    const handleUnbookPosition = (positionId) => {
        router.delete(`/positions/${positionId}/book`, {
            onSuccess: () => router.reload({ only: ['staffings'] })
        });
    };

    const handleDeleteSection = (sectionId) => {
        if (confirm('Are you sure you want to delete this section?')) {
            router.delete(`/staffings/${sectionId}`, {
                onSuccess: () => router.reload({ only: ['staffings'] })
            });
        }
    };

    const handleDeletePosition = (positionId) => {
        if (confirm('Are you sure you want to remove this position?')) {
            router.delete(`/positions/${positionId}`, {
                onSuccess: () => router.reload({ only: ['staffings'] })
            });
        }
    };

    const handleEditPosition = (position) => {
        setEditingPosition(position);
        setEditData({
            position_id: position.position_id,
            position_name: position.position_name,
            start_time: position.start_time || '',
            end_time: position.end_time || '',
        });
        setShowEditPosition(true);
    };

    const handleUpdatePosition = (e) => {
        e.preventDefault();
        if (editingPosition) {
            putPosition(`/positions/${editingPosition.id}`, {
                onSuccess: () => {
                    setShowEditPosition(false);
                    resetEdit();
                    setEditingPosition(null);
                    router.reload({ only: ['staffings'] });
                },
            });
        }
    };

    const handleEditModalClose = () => {
        setShowEditPosition(false);
        resetEdit();
        setEditingPosition(null);
    };

    const handleModalClose = () => {
        setShowAddPosition(false);
        setIsLocalPosition(false);
        setSearchQuery('');
        resetPosition();
    };

    const handleSaveStaffingDescription = (e) => {
        e.preventDefault();
        router.put(`/events/${event.id}`, {
            calendar_id: event.calendar_id,
            title: event.title,
            short_description: event.short_description,
            long_description: event.long_description,
            staffing_description: staffingDescData.staffing_description,
            featured_airports: event.featured_airports || [],
            start_datetime: event.start_datetime?.local,
            end_datetime: event.end_datetime?.local,
            discord_staffing_channel_id: event.discord_staffing_channel_id,
        }, {
            preserveScroll: true,
            onError: (errors) => {
                console.error('Validation errors:', errors);
                alert('Failed to save: ' + JSON.stringify(errors));
            },
        });
    };

    return (
        <>
            <Head title={`Manage Staffing - ${event.title}`} />
            <DndProvider backend={HTML5Backend}>
                <Layout auth={auth}>

                        {/* Header card */}
                        <div className="border border-neutral-200 dark:border-neutral-700">
                            <div className="bg-secondary dark:bg-neutral-800 border-b border-neutral-200 dark:border-neutral-700 px-6 py-4">
                                <div className="flex justify-between items-center gap-4">
                                    <div>
                                        <h1 className="text-lg font-semibold text-white">Manage Staffing</h1>
                                        <p className="text-sm text-white/70 mt-0.5">{event.title}</p>
                                    </div>
                                    <div className="flex gap-3 flex-wrap justify-end">
                                        <Link href={`/events/${event.id}`}>
                                            <Button variant="secondary">Back to Event</Button>
                                        </Link>
                                        {event.discord_staffing_channel_id && initialStaffings.length > 0 && (
                                            <Button
                                                variant="primary"
                                                onClick={handleSetupDiscord}
                                                disabled={settingUpDiscord || event.discord_staffing_message_id}
                                            >
                                                {event.discord_staffing_message_id
                                                    ? '✓ Already Set Up in Discord'
                                                    : settingUpDiscord
                                                        ? 'Setting up...'
                                                        : '📣 Setup in Discord'}
                                            </Button>
                                        )}
                                        <Button variant="warning" onClick={handleResetStaffing} disabled={resetting}>
                                            {resetting ? 'Resetting...' : 'Reset All Bookings'}
                                        </Button>
                                        <Button variant="success" onClick={() => setShowAddSection(true)}>
                                            + Add Section
                                        </Button>
                                    </div>
                                </div>
                            </div>

                            {/* Staffing Description */}
                            <div className="bg-white dark:bg-neutral-800 px-6 py-4">
                                <form onSubmit={handleSaveStaffingDescription} className="flex flex-col gap-3">
                                    <label htmlFor="staffing_description" className={labelClass}>
                                        Staffing Description <span className="font-normal text-neutral-400">(shown in Discord)</span>
                                    </label>
                                    <MarkdownEditor
                                        value={staffingDescData.staffing_description}
                                        onChange={(value) => setStaffingDescData('staffing_description', value)}
                                        placeholder="Add a description for the staffing that will be shown in Discord (markdown supported)..."
                                    />
                                    <div className="flex justify-end">
                                        <Button type="submit" variant="success" disabled={updatingDesc} size="sm">
                                            {updatingDesc ? 'Saving...' : 'Save Description'}
                                        </Button>
                                    </div>
                                </form>
                            </div>
                        </div>

                        {/* Staffing Sections */}
                        {initialStaffings.length > 0 ? (
                            initialStaffings.map((staffing) => (
                                <StaffingSection
                                    key={staffing.id}
                                    staffing={staffing}
                                    auth={auth}
                                    handleUnbookPosition={handleUnbookPosition}
                                    handleDeletePosition={handleDeletePosition}
                                    handleEditPosition={handleEditPosition}
                                    handleDeleteSection={handleDeleteSection}
                                    setSelectedSection={setSelectedSection}
                                    setShowAddPosition={setShowAddPosition}
                                />
                            ))
                        ) : (
                            <div className="border border-neutral-200 dark:border-neutral-700 bg-white dark:bg-neutral-800">
                                <p className="text-center text-neutral-500 dark:text-neutral-400 py-16">
                                    No staffing sections yet. Click "Add Section" to get started.
                                </p>
                            </div>
                        )}

                        {/* Add Section Modal */}
                        <Modal show={showAddSection} onClose={() => setShowAddSection(false)}>
                            <form onSubmit={handleAddSection} className="p-6 flex flex-col gap-6">
                                <h3 className="text-lg font-semibold text-neutral-900 dark:text-neutral-100">Add Staffing Section</h3>
                                <div className={sectionClass}>
                                    <label htmlFor="section_name" className={labelClass}>Section Name</label>
                                    <Input
                                        id="section_name"
                                        value={sectionData.name}
                                        onChange={(e) => setSectionData('name', e.target.value)}
                                        placeholder="e.g., Tower, Approach, Center"
                                        required
                                    />
                                </div>
                                <div className="flex justify-end gap-3">
                                    <Button type="button" variant="outline-danger" onClick={() => setShowAddSection(false)}>Cancel</Button>
                                    <Button type="submit" variant="success" disabled={sectionProcessing}>
                                        {sectionProcessing ? 'Creating...' : 'Add Section'}
                                    </Button>
                                </div>
                            </form>
                        </Modal>

                        {/* Add Position Modal */}
                        <Modal show={showAddPosition} onClose={handleModalClose} maxWidth="2xl">
                            <form onSubmit={handleAddPosition} className="p-6 flex flex-col gap-6">
                                <h3 className="text-lg font-semibold text-neutral-900 dark:text-neutral-100">Add Position</h3>

                                {errors.position_id && (
                                    <div className="p-3 border border-danger bg-danger/10 text-sm font-medium text-neutral-900 dark:text-neutral-100">
                                        {errors.position_id}
                                    </div>
                                )}

                                {/* Toggle */}
                                <div className="flex gap-2">
                                    <Button
                                        type="button"
                                        variant={!isLocalPosition ? 'secondary' : 'outline-secondary'}
                                        onClick={() => {
                                            setIsLocalPosition(false);
                                            setPositionData({ ...positionData, is_local: false });
                                        }}
                                    >
                                        Control Center Positions
                                    </Button>
                                    <Button
                                        type="button"
                                        variant={isLocalPosition ? 'warning' : 'outline-warning'}
                                        onClick={() => {
                                            setIsLocalPosition(true);
                                            setPositionData({ position_id: '', position_name: '', is_local: true });
                                        }}
                                    >
                                        Custom Local Position
                                    </Button>
                                </div>

                                {!isLocalPosition ? (
                                    <>
                                        <div className={sectionClass}>
                                            <label className={labelClass}>Search Positions</label>
                                            <Input
                                                value={searchQuery}
                                                onChange={(e) => setSearchQuery(e.target.value)}
                                                placeholder="Search by callsign or name..."
                                            />
                                        </div>

                                        <div className={sectionClass}>
                                            <label className={labelClass}>Select Position from Control Center</label>
                                            {loadingPositions ? (
                                                <p className="text-center text-neutral-500 dark:text-neutral-400 py-8">Loading positions...</p>
                                            ) : (
                                                <div className="max-h-96 overflow-y-auto border border-neutral-300 dark:border-neutral-700 flex flex-col">
                                                    {filteredPositions.length > 0 ? (
                                                        filteredPositions.map((position) => (
                                                            <button
                                                                key={position.callsign}
                                                                type="button"
                                                                onClick={() => handlePositionSelect(position)}
                                                                className={`w-full text-left px-3 py-2 text-sm transition-colors border-b border-neutral-200 dark:border-neutral-700 last:border-0
                                                                    ${positionData.position_id === position.callsign
                                                                        ? 'bg-secondary text-white'
                                                                        : 'text-neutral-900 dark:text-neutral-100 hover:bg-secondary hover:text-white'
                                                                    }`}
                                                            >
                                                                <div className="flex items-center justify-between">
                                                                    <div>
                                                                        <span className="font-mono font-semibold">{position.callsign}</span>
                                                                        <span className="ml-3">{position.name}</span>
                                                                    </div>
                                                                    {position.frequency && (
                                                                        <span className="text-xs opacity-70">{position.frequency}</span>
                                                                    )}
                                                                </div>
                                                            </button>
                                                        ))
                                                    ) : (
                                                        <p className="text-center text-neutral-500 dark:text-neutral-400 py-8">No positions found.</p>
                                                    )}
                                                </div>
                                            )}
                                        </div>

                                        {positionData.position_id && (
                                            <div className="p-3 border border-neutral-200 dark:border-neutral-700 bg-neutral-50 dark:bg-neutral-700/30">
                                                <p className="text-xs text-neutral-500 dark:text-neutral-400 mb-1">Selected:</p>
                                                <p className="font-mono text-sm font-semibold text-secondary dark:text-primary">{positionData.position_id}</p>
                                                <p className="text-sm text-neutral-600 dark:text-neutral-400">{positionData.position_name}</p>
                                            </div>
                                        )}
                                    </>
                                ) : (
                                    <>
                                        <div className="p-3 border border-warning/40 bg-warning/5 text-sm text-neutral-700 dark:text-neutral-300">
                                            <strong>Custom Position:</strong> This position will not be synced with Control Center or VATSIM.
                                        </div>
                                        <div className="grid grid-cols-2 gap-4">
                                            <div className={sectionClass}>
                                                <label htmlFor="position_id" className={labelClass}>Position ID *</label>
                                                <Input
                                                    id="position_id"
                                                    value={positionData.position_id}
                                                    onChange={(e) => setPositionData('position_id', e.target.value)}
                                                    placeholder="e.g., CUSTOM_TWR"
                                                    required
                                                />
                                            </div>
                                            <div className={sectionClass}>
                                                <label htmlFor="position_name" className={labelClass}>Position Name *</label>
                                                <Input
                                                    id="position_name"
                                                    value={positionData.position_name}
                                                    onChange={(e) => setPositionData('position_name', e.target.value)}
                                                    placeholder="e.g., Custom Tower"
                                                    required
                                                />
                                            </div>
                                        </div>
                                    </>
                                )}

                                {/* Time fields */}
                                <div className="grid grid-cols-2 gap-4 pt-4 border-t border-neutral-200 dark:border-neutral-700">
                                    <div className={sectionClass}>
                                        <label htmlFor="start_time" className={labelClass}>Start Time <span className="font-normal text-neutral-400">(optional)</span></label>
                                        <TimeInput
                                            value={positionData.start_time}
                                            onChange={(time) => setPositionData('start_time', time)}
                                            placeholder="HH:mm (e.g., 18:00)"
                                        />
                                    </div>
                                    <div className={sectionClass}>
                                        <label htmlFor="end_time" className={labelClass}>End Time <span className="font-normal text-neutral-400">(optional)</span></label>
                                        <TimeInput
                                            value={positionData.end_time}
                                            onChange={(time) => setPositionData('end_time', time)}
                                            placeholder="HH:mm (e.g., 22:00)"
                                        />
                                    </div>
                                </div>

                                <div className="flex justify-end gap-3">
                                    <Button type="button" variant="outline-danger" onClick={handleModalClose}>Cancel</Button>
                                    <Button
                                        type="submit"
                                        variant="success"
                                        disabled={positionProcessing || !positionData.position_id || !positionData.position_name}
                                    >
                                        {positionProcessing ? 'Adding...' : 'Add Position'}
                                    </Button>
                                </div>
                            </form>
                        </Modal>

                        {/* Edit Position Modal */}
                        <Modal show={showEditPosition} onClose={handleEditModalClose} maxWidth="xl">
                            <form onSubmit={handleUpdatePosition} className="p-6 flex flex-col gap-6">
                                <h3 className="text-lg font-semibold text-neutral-900 dark:text-neutral-100">Edit Position</h3>

                                {errors.position_id && (
                                    <div className="p-3 border border-danger bg-danger/10 text-sm font-medium text-neutral-900 dark:text-neutral-100">
                                        {errors.position_id}
                                    </div>
                                )}

                                <div className="grid grid-cols-2 gap-4">
                                    <div className={sectionClass}>
                                        <label htmlFor="edit_position_id" className={labelClass}>Position ID *</label>
                                        <Input
                                            id="edit_position_id"
                                            value={editData.position_id}
                                            onChange={(e) => setEditData('position_id', e.target.value)}
                                            placeholder="e.g., EKCH_TWR"
                                            required
                                        />
                                    </div>
                                    <div className={sectionClass}>
                                        <label htmlFor="edit_position_name" className={labelClass}>Position Name *</label>
                                        <Input
                                            id="edit_position_name"
                                            value={editData.position_name}
                                            onChange={(e) => setEditData('position_name', e.target.value)}
                                            placeholder="e.g., Copenhagen Tower"
                                            required
                                        />
                                    </div>
                                </div>

                                <div className="grid grid-cols-2 gap-4 pt-4 border-t border-neutral-200 dark:border-neutral-700">
                                    <div className={sectionClass}>
                                        <label htmlFor="edit_start_time" className={labelClass}>Start Time <span className="font-normal text-neutral-400">(optional)</span></label>
                                        <TimeInput
                                            value={editData.start_time}
                                            onChange={(time) => setEditData('start_time', time)}
                                            placeholder="HH:mm (e.g., 18:00)"
                                        />
                                    </div>
                                    <div className={sectionClass}>
                                        <label htmlFor="edit_end_time" className={labelClass}>End Time <span className="font-normal text-neutral-400">(optional)</span></label>
                                        <TimeInput
                                            value={editData.end_time}
                                            onChange={(time) => setEditData('end_time', time)}
                                            placeholder="HH:mm (e.g., 22:00)"
                                        />
                                    </div>
                                </div>

                                <div className="flex justify-end gap-3">
                                    <Button type="button" variant="outline-danger" onClick={handleEditModalClose}>Cancel</Button>
                                    <Button
                                        type="submit"
                                        variant="success"
                                        disabled={editProcessing || !editData.position_id || !editData.position_name}
                                    >
                                        {editProcessing ? 'Saving...' : 'Update Position'}
                                    </Button>
                                </div>
                            </form>
                        </Modal>

                </Layout>
            </DndProvider>
        </>
    );
}