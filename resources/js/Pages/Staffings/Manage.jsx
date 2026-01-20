import { useState, useCallback, useRef, useEffect } from 'react';
import { useForm, usePage, router, Link, Head } from '@inertiajs/react';
import { DndProvider, useDrag, useDrop } from 'react-dnd';
import { HTML5Backend } from 'react-dnd-html5-backend';
import Layout from '../../Layouts/Layout';
import Button from '../../Components/Button';
import Input from '../../Components/Input';
import Textarea from '../../Components/Textarea';
import MarkdownEditor from '../../Components/MarkdownEditor';
import Modal from '../../Components/Modal';
import TimeInput from '../../Components/TimeInput';
import { TimeDisplay } from '../../Components/DateTimeDisplay';

const POSITION_TYPE = 'POSITION';

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
            if (!ref.current) {
                return;
            }
            const dragIndex = item.index;
            const hoverIndex = index;

            if (dragIndex === hoverIndex) {
                return;
            }

            const hoverBoundingRect = ref.current?.getBoundingClientRect();
            const hoverMiddleY = (hoverBoundingRect.bottom - hoverBoundingRect.top) / 2;
            const clientOffset = monitor.getClientOffset();
            const hoverClientY = clientOffset.y - hoverBoundingRect.top;

            if (dragIndex < hoverIndex && hoverClientY < hoverMiddleY) {
                return;
            }
            if (dragIndex > hoverIndex && hoverClientY > hoverMiddleY) {
                return;
            }

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
            className={`flex items-center justify-between p-4 bg-grey-50 hover:bg-grey-100 transition-colors cursor-move ${
                isDragging ? 'opacity-50' : ''
            } ${isOver ? 'border-secondary' : ''}`}
            style={{ border: '2px solid var(--color-grey-200)' }}
        >
            <div className="flex items-center gap-3 flex-1">
                <span className="text-grey-400">⋮⋮</span>
                <div className="flex-1">
                    <div className="flex items-center gap-2">
                        <span className="font-mono text-sm font-semibold text-secondary dark:text-primary">
                            {position.position_id}
                        </span>
                        {position.is_local && (
                            <span className="inline-flex items-center px-2 py-0.5 text-xs font-medium bg-warning text-white">
                                LOCAL
                            </span>
                        )}
                    </div>
                    <span className="text-sm text-grey-700 dark:text-dark-text">
                        {position.position_name}
                    </span>
                    {(position.start_time || position.end_time) && (
                        <div className="text-xs text-grey-500 dark:text-dark-text-secondary mt-1">
                            {position.start_time && <TimeDisplay datetime={position.start_time} />}
                            {position.start_time && position.end_time && <span> - </span>}
                            {position.end_time && <TimeDisplay datetime={position.end_time} />}
                        </div>
                    )}
                </div>
            </div>
            <div className="flex items-center gap-3">
                {(position.booked_by_user_id || position.vatsim_cid) ? (
                    <>
                        <span className="text-sm text-success font-medium">
                            {position.booked_by_user_id 
                                ? `Booked by ${position.booked_by?.name || 'Unknown User'}`
                                : `Booked by ${position.vatsim_cid} (Discord)`
                            }
                        </span>
                        {(auth.user?.id === position.booked_by_user_id ||
                          auth.user?.permissions?.includes('unbook-any-position')) && (
                            <Button
                                variant="warning"
                                onClick={() => handleUnbookPosition(position.id)}
                            >
                                Unbook
                            </Button>
                        )}
                    </>
                ) : (
                    <span className="text-sm text-grey-500 dark:text-dark-text-secondary font-medium">Available</span>
                )}
                {auth.user?.permissions?.includes('manage-staffings') && (
                    <>
                        <Button
                            variant="secondary"
                            onClick={() => handleEditPosition(position)}
                        >
                            Edit
                        </Button>
                        <Button
                            variant="danger"
                            onClick={() => handleDeletePosition(position.id)}
                        >
                            Remove
                        </Button>
                    </>
                )}
            </div>
        </div>
    );
}

function StaffingSection({ staffing, auth, handleUnbookPosition, handleDeletePosition, handleEditPosition, handleDeleteSection, setSelectedSection, setShowAddPosition }) {
    const [positions, setPositions] = useState(staffing.positions || []);

    // Sync positions when staffing.positions changes (after reload)
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

        router.post('/positions/reorder', {
            positions: updatedPositions,
        }, {
            preserveScroll: true,
            onSuccess: () => {
                // Positions saved successfully
            },
        });
    }, [positions, staffing.id]);

    // Save order when positions change (debounced via useEffect)
    useEffect(() => {
        if (positions.length > 0 && positions !== staffing.positions) {
            const timer = setTimeout(() => {
                savePositionOrder();
            }, 500);
            return () => clearTimeout(timer);
        }
    }, [positions, savePositionOrder, staffing.positions]);

    return (
        <div className="bg-white dark:bg-dark-bg-secondary" style={{ boxShadow: 'var(--shadow-card)' }}>
            {/* Section Header */}
            <div className="bg-grey-100 dark:bg-dark-bg-tertiary px-6 py-4 border-b-2 border-grey-200 dark:border-dark-border">
                <div className="flex justify-between items-center">
                    <div>
                        <h2 className="text-xl font-semibold text-grey-900">{staffing.name}</h2>
                        {staffing.synced_to_vatsim && (
                            <p className="text-sm text-success mt-1">
                                ✓ Synced to VATSIM on {new Date(staffing.synced_at).toLocaleString()}
                            </p>
                        )}
                    </div>
                    <div className="flex gap-2">
                        <Button
                            variant="secondary"
                            onClick={() => {
                                setSelectedSection(staffing.id);
                                setShowAddPosition(true);
                            }}
                        >
                            + Add Position
                        </Button>
                        <Button
                            variant="danger"
                            onClick={() => handleDeleteSection(staffing.id)}
                        >
                            Delete Section
                        </Button>
                    </div>
                </div>
            </div>

            {/* Positions */}
            <div className="p-6">
                {positions.length > 0 ? (
                    <div className="space-y-2">
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
                    <div className="text-center py-12">
                        <p className="text-grey-500 dark:text-dark-text-secondary">No positions added yet</p>
                    </div>
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

    const { data: staffingDescData, setData: setStaffingDescData, put: updateStaffingDesc, processing: updatingDesc } = useForm({
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

    // Fetch API positions when modal opens
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

        if (!confirm('This will post a staffing message to Discord. Continue?')) {
            return;
        }

        setSettingUpDiscord(true);
        try {
            const response = await fetch('/api/staffing/setup', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                },
                body: JSON.stringify({
                    id: initialStaffings[0].id
                }),
            });

            const data = await response.json();
            
            if (response.ok) {
                alert('✅ Staffing setup initiated! Check your Discord channel.');
            } else {
                alert('❌ Error: ' + (data.error || data.message || 'Failed to setup Discord staffing'));
            }
        } catch (error) {
            console.error('Failed to setup Discord:', error);
            alert('❌ Failed to connect to Discord bot. Please check:\n\n' +
                  '1. Bot server is running\n' +
                  '2. DISCORD_API_URL is configured correctly\n' +
                  '3. Bot can reach Laravel API');
        } finally {
            setSettingUpDiscord(false);
        }
    };

    const handleResetStaffing = () => {
        if (!confirm('⚠️ This will clear ALL bookings for this event.\n\n' +
                     'All positions will be reset to available.\n' +
                     'Control Center bookings will be deleted.\n' +
                     'Discord message will be updated.\n\n' +
                     'Continue?')) {
            return;
        }

        setResetting(true);
        router.post(`/events/${event.id}/staffings/reset`, {}, {
            preserveScroll: true,
            onSuccess: () => {
                setResetting(false);
            },
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
        setPositionData({
            position_id: position.callsign,
            position_name: position.name,
            is_local: false,
        });
    };

    const handleBookPosition = (positionId) => {
        router.post(`/positions/${positionId}/book`, {}, {
            onSuccess: () => {
                router.reload({ only: ['staffings'] });
            }
        });
    };

    const handleUnbookPosition = (positionId) => {
        router.delete(`/positions/${positionId}/book`, {
            onSuccess: () => {
                router.reload({ only: ['staffings'] });
            }
        });
    };

    const handleDeleteSection = (sectionId) => {
        if (confirm('Are you sure you want to delete this section?')) {
            router.delete(`/staffings/${sectionId}`, {
                onSuccess: () => {
                    router.reload({ only: ['staffings'] });
                }
            });
        }
    };

    const handleDeletePosition = (positionId) => {
        if (confirm('Are you sure you want to remove this position?')) {
            router.delete(`/positions/${positionId}`, {
                onSuccess: () => {
                    router.reload({ only: ['staffings'] });
                }
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
        
        // Use Inertia router with all required data
        router.put(`/events/${event.id}`, {
            calendar_id: event.calendar_id,
            title: event.title,
            short_description: event.short_description,
            long_description: event.long_description,
            staffing_description: staffingDescData.staffing_description,
            featured_airports: event.featured_airports || [],
            start_datetime: event.start_datetime,
            end_datetime: event.end_datetime,
            discord_staffing_channel_id: event.discord_staffing_channel_id,
        }, {
            preserveScroll: true,
            onSuccess: () => {
                console.log('Staffing description saved successfully');
            },
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
                    <div className="max-w-6xl mx-auto px-4 py-8">
                    {/* Header */}
                    <div className="mb-8">
                        <div className="bg-white dark:bg-dark-bg-secondary" style={{ boxShadow: 'var(--shadow-card)' }}>
                            <div className="bg-secondary dark:bg-dark-bg-tertiary px-6 py-4">
                                <div className="flex justify-between items-center">
                                    <div>
                                        <h1 className="text-2xl font-semibold text-white">Manage Staffing</h1>
                                        <p className="text-white text-opacity-90 mt-1">{event.title}</p>
                                    </div>
                                    <div className="flex gap-3">
                                        <Link href={`/events/${event.id}`}>
                                            <Button variant="outline">Back to Event</Button>
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
                                        <Button 
                                            variant="warning" 
                                            onClick={handleResetStaffing}
                                            disabled={resetting}
                                        >
                                            {resetting ? 'Resetting...' : '🔄 Reset All Bookings'}
                                        </Button>
                                        <Button variant="success" onClick={() => setShowAddSection(true)}>
                                            + Add Section
                                        </Button>
                                    </div>
                                </div>
                            </div>
                            
                            {/* Staffing Description */}
                            <div className="px-6 py-4 border-t border-grey-200 dark:border-dark-border">
                                <form onSubmit={handleSaveStaffingDescription}>
                                    <label htmlFor="staffing_description" className="block text-sm font-medium text-gray-700 dark:text-dark-text mb-2">
                                        Staffing Description (shown in Discord)
                                    </label>
                                    
                                    <MarkdownEditor
                                        value={staffingDescData.staffing_description}
                                        onChange={(value) => setStaffingDescData('staffing_description', value)}
                                        placeholder="Add a description for the staffing that will be shown in Discord (markdown supported)..."
                                    />
                                    <div className="flex justify-end mt-3">
                                        <Button type="submit" variant="success" disabled={updatingDesc} size="sm">
                                            {updatingDesc ? 'Saving...' : 'Save Description'}
                                        </Button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>

                    {/* Staffing Sections */}
                    <div className="space-y-6">
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
                            <div className="bg-white dark:bg-dark-bg-secondary" style={{ boxShadow: 'var(--shadow-card)' }}>
                                <div className="text-center py-16 px-6">
                                    <p className="text-grey-500 dark:text-dark-text-secondary text-lg">No staffing sections yet.</p>
                                    <p className="text-grey-400 mt-2">Click "Add Section" to get started.</p>
                                </div>
                            </div>
                        )}
                    </div>

                    {/* Add Section Modal */}
                    <Modal show={showAddSection} onClose={() => setShowAddSection(false)}>
                        <form onSubmit={handleAddSection} className="p-6">
                            <h3 className="text-xl font-semibold text-grey-900 mb-6">Add Staffing Section</h3>
                            <div className="mb-6">
                                <label htmlFor="section_name" className="block text-sm font-medium text-gray-700 dark:text-dark-text mb-2">
                                    Section Name
                                </label>
                                <Input
                                    id="section_name"
                                    value={sectionData.name}
                                    onChange={(e) => setSectionData('name', e.target.value)}
                                    placeholder="e.g., Tower, Approach, Center"
                                    required
                                />
                            </div>
                            <div className="flex justify-end gap-3">
                                <Button type="button" variant="outline-danger" onClick={() => setShowAddSection(false)}>
                                    Cancel
                                </Button>
                                <Button type="submit" variant="success" disabled={sectionProcessing}>
                                    {sectionProcessing ? 'Creating...' : 'Add Section'}
                                </Button>
                            </div>
                        </form>
                    </Modal>

                    {/* Add Position Modal */}
                    <Modal show={showAddPosition} onClose={handleModalClose} maxWidth="2xl">
                        <form onSubmit={handleAddPosition} className="p-6">
                            <h3 className="text-xl font-semibold text-grey-900 mb-6">Add Position</h3>
                            
                            {/* Display validation errors */}
                            {errors.position_id && (
                                <div className="mb-4 p-3 bg-danger bg-opacity-10 border-2 border-danger text-gray-900 text-sm font-medium">
                                    {errors.position_id}
                                </div>
                            )}
                            
                            {/* Toggle between API and Local */}
                            <div className="mb-6 flex gap-2">
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
                                    {/* Search for API positions */}
                                    <div className="mb-4">
                                        <label className="block text-sm font-medium text-gray-700 dark:text-dark-text mb-2">
                                            Search Positions
                                        </label>
                                        <Input
                                            value={searchQuery}
                                            onChange={(e) => setSearchQuery(e.target.value)}
                                            placeholder="Search by callsign or name..."
                                        />
                                    </div>

                                    {/* API Position List */}
                                    <div className="mb-6">
                                        <label className="block text-sm font-medium text-gray-700 dark:text-dark-text mb-2">
                                            Select Position from Control Center
                                        </label>
                                        {loadingPositions ? (
                                            <div className="text-center py-8">
                                                <p className="text-grey-500 dark:text-dark-text-secondary">Loading positions...</p>
                                            </div>
                                        ) : (
                                            <div className="max-h-96 overflow-y-auto border-2 border-grey-300 p-2 space-y-1">
                                                {filteredPositions.length > 0 ? (
                                                    filteredPositions.map((position) => (
                                                        <button
                                                            key={position.callsign}
                                                            type="button"
                                                            onClick={() => handlePositionSelect(position)}
                                                            className={`w-full text-left px-3 py-2 hover:bg-secondary hover:text-white transition-colors ${
                                                                positionData.position_id === position.callsign ? 'bg-secondary text-white' : ''
                                                            }`}
                                                        >
                                                            <div className="flex items-center justify-between">
                                                                <div>
                                                                    <span className="font-mono text-sm font-semibold">{position.callsign}</span>
                                                                    <span className="ml-3 text-sm">{position.name}</span>
                                                                </div>
                                                                {position.frequency && (
                                                                    <span className="text-xs text-grey-500 dark:text-dark-text-secondary">{position.frequency}</span>
                                                                )}
                                                            </div>
                                                        </button>
                                                    ))
                                                ) : (
                                                    <div className="text-center py-8">
                                                        <p className="text-grey-500 dark:text-dark-text-secondary">No positions found</p>
                                                    </div>
                                                )}
                                            </div>
                                        )}
                                    </div>

                                    {/* Selected position preview */}
                                    {positionData.position_id && (
                                        <div className="mb-6 p-4 bg-grey-50 dark:bg-dark-bg-tertiary border-2 border-grey-200 dark:border-dark-border">
                                            <p className="text-sm font-medium text-grey-700 dark:text-dark-text mb-1">Selected:</p>
                                            <p className="font-mono text-sm font-semibold text-secondary dark:text-primary">{positionData.position_id}</p>
                                            <p className="text-sm text-grey-600 dark:text-dark-text-secondary">{positionData.position_name}</p>
                                        </div>
                                    )}
                                </>
                            ) : (
                                <>
                                    {/* Custom local position fields */}
                                    <div className="mb-6 p-4 bg-warning bg-opacity-10 border-2 border-warning">
                                        <p className="text-sm text-grey-700 dark:text-dark-text">
                                            <strong>Custom Position:</strong> This position will not be synced with Control Center or VATSIM.
                                        </p>
                                    </div>

                                    <div className="grid grid-cols-2 gap-4 mb-6">
                                        <div>
                                            <label htmlFor="position_id" className="block text-sm font-medium text-gray-700 dark:text-dark-text mb-2">
                                                Position ID *
                                            </label>
                                            <Input
                                                id="position_id"
                                                value={positionData.position_id}
                                                onChange={(e) => setPositionData('position_id', e.target.value)}
                                                placeholder="e.g., CUSTOM_TWR"
                                                required
                                            />
                                        </div>
                                        <div>
                                            <label htmlFor="position_name" className="block text-sm font-medium text-gray-700 dark:text-dark-text mb-2">
                                                Position Name *
                                            </label>
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

                            {/* Time fields (shown for both API and local positions) */}
                            <div className="grid grid-cols-2 gap-4 mb-6 pt-4 border-t-2 border-grey-200 dark:border-dark-border">
                                <div>
                                    <label htmlFor="start_time" className="block text-sm font-medium text-gray-700 dark:text-dark-text mb-2">
                                        Start Time (Optional)
                                    </label>
                                    <TimeInput
                                        value={positionData.start_time}
                                        onChange={(time) => setPositionData('start_time', time)}
                                        placeholder="HH:mm (e.g., 18:00)"
                                    />
                                </div>
                                <div>
                                    <label htmlFor="end_time" className="block text-sm font-medium text-gray-700 dark:text-dark-text mb-2">
                                        End Time (Optional)
                                    </label>
                                    <TimeInput
                                        value={positionData.end_time}
                                        onChange={(time) => setPositionData('end_time', time)}
                                        placeholder="HH:mm (e.g., 22:00)"
                                    />
                                </div>
                            </div>

                            <div className="flex justify-end gap-3">
                                <Button type="button" variant="outline-danger" onClick={handleModalClose}>
                                    Cancel
                                </Button>
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
                        <form onSubmit={handleUpdatePosition} className="p-6">
                            <h3 className="text-xl font-semibold text-grey-900 mb-6">Edit Position</h3>
                            
                            {/* Display validation errors */}
                            {errors.position_id && (
                                <div className="mb-4 p-3 bg-danger bg-opacity-10 border-2 border-danger text-gray-900 text-sm font-medium">
                                    {errors.position_id}
                                </div>
                            )}
                            
                            <div className="grid grid-cols-2 gap-4 mb-6">
                                <div>
                                    <label htmlFor="edit_position_id" className="block text-sm font-medium text-gray-700 dark:text-dark-text mb-2">
                                        Position ID *
                                    </label>
                                    <Input
                                        id="edit_position_id"
                                        value={editData.position_id}
                                        onChange={(e) => setEditData('position_id', e.target.value)}
                                        placeholder="e.g., EKCH_TWR"
                                        required
                                    />
                                </div>
                                <div>
                                    <label htmlFor="edit_position_name" className="block text-sm font-medium text-gray-700 dark:text-dark-text mb-2">
                                        Position Name *
                                    </label>
                                    <Input
                                        id="edit_position_name"
                                        value={editData.position_name}
                                        onChange={(e) => setEditData('position_name', e.target.value)}
                                        placeholder="e.g., Copenhagen Tower"
                                        required
                                    />
                                </div>
                            </div>

                            {/* Time fields */}
                            <div className="grid grid-cols-2 gap-4 mb-6 pt-4 border-t-2 border-grey-200 dark:border-dark-border">
                                <div>
                                    <label htmlFor="edit_start_time" className="block text-sm font-medium text-gray-700 dark:text-dark-text mb-2">
                                        Start Time (Optional)
                                    </label>
                                    <TimeInput
                                        value={editData.start_time}
                                        onChange={(time) => setEditData('start_time', time)}
                                        placeholder="HH:mm (e.g., 18:00)"
                                    />
                                </div>
                                <div>
                                    <label htmlFor="edit_end_time" className="block text-sm font-medium text-gray-700 dark:text-dark-text mb-2">
                                        End Time (Optional)
                                    </label>
                                    <TimeInput
                                        value={editData.end_time}
                                        onChange={(time) => setEditData('end_time', time)}
                                        placeholder="HH:mm (e.g., 22:00)"
                                    />
                                </div>
                            </div>

                            <div className="flex justify-end gap-3">
                                <Button type="button" variant="outline-danger" onClick={handleEditModalClose}>
                                    Cancel
                                </Button>
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
                </div>
            </Layout>
        </DndProvider>
        </>
    );
}
