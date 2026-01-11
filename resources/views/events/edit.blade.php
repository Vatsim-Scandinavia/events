@extends('layouts.app')
@section('title', 'Edit Event')

@section('content')
<div class="container-fluid">
    <div class="row">
        {{-- Left Column: Main Edit Form --}}
        <div class="col-xl-8 col-lg-7 mb-4">
            <div class="card shadow mb-4">
                <div class="card-header bg-primary py-3 d-flex flex-row align-items-center justify-content-between">
                    <h6 class="m-0 fw-bold text-white">Edit Event Details</h6>
                    <span class="badge bg-white text-primary">ID: #{{ $event->id }}</span>
                </div>
                <div class="card-body">
                    <form action="{{ route('events.update', $event) }}" method="post" enctype="multipart/form-data">
                        @csrf
                        @method('PATCH')

                        {{-- Series Warnings --}}
                        @if($event->parent_id != null)
                            <div class="alert alert-info border-left-info">
                                <i class="fas fa-circle-info"></i> <strong>Note:</strong> You are editing a <strong>single occurrence</strong>.
                            </div>
                        @elseif($event->parent_id == null && $event->recurrence_interval != null)
                            <div class="alert alert-warning border-left-warning">
                                <i class="fas fa-exclamation-triangle"></i> <strong>Warning:</strong> You are editing the <strong>entire series</strong>. Saving changes will apply to all occurrences of this event.
                            </div>
                        @endif

                        <div class="form-group mb-4">
                            <label for="title" class="form-label fw-bold">Event Title</label>
                            <input type="text" name="title" id="title" class="form-control @error('title') is-invalid @enderror" value="{{ old('title', $event->title) }}" required>
                        </div>

                        <div class="form-group mb-4">
                            <label for="calendar" class="form-label fw-bold">Calendar</label>
                            <select name="calendar_id" id="calendar" class="form-control @error('calendar_id') is-invalid @enderror" required>
                                @foreach ($calendars as $calendar)
                                    @can('view', $calendar)
                                        <option value="{{ $calendar->id }}" @selected(old('calendar_id', $event->calendar_id) == $calendar->id)>
                                            {{ $calendar->name }}
                                        </option>
                                    @endcan
                                @endforeach
                            </select>
                        </div>

                        <div class="form-group mb-4">
                            <label for="short_description" class="form-label fw-bold">Short Description</label>
                            <textarea class="form-control" name="short_description" id="short_description" rows="2">{{ old('short_description', $event->short_description) }}</textarea>
                        </div>

                        <div class="form-group mb-4">
                            <label for="long_description" class="form-label fw-bold">Event Description</label>
                            <textarea class="form-control" name="long_description" id="long_description" rows="8">{{ old('long_description', $event->long_description) }}</textarea>
                        </div>

                        <div class="form-group mb-4">
                            <label for="customFile" class="form-label fw-bold">Banner Image</label>
                            <div class="custom-file">
                                <input type="file" class="custom-file-input" id="customFile" name="image" accept="image/*">
                                <label class="custom-file-label" for="customFile">Choose new image...</label>
                            </div>
                            @if ($event->image)
                                <div class="mt-3">
                                    <small class="text-muted d-block mb-1">Current Banner:</small>
                                    <img src="{{ asset('storage/banners/' . $event->image) }}" alt="Event Image" class="img-thumbnail" width="200">
                                </div>
                            @endif
                        </div>

                        <div class="row mb-4">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="start_date" class="form-label fw-bold text-primary">Start Date & Time</label>
                                    <input type="text" name="start_date" id="start_date" class="form-control border-primary" 
                                        value="{{ old('start_date', $event->instances->sortBy('start_time')->first()?->start_time->format('Y-m-d H:i')) }}">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="end_date" class="form-label fw-bold text-primary">End Date & Time</label>
                                    <input type="text" name="end_date" id="end_date" class="form-control border-primary" 
                                        value="{{ old('end_date', $event->instances->sortBy('start_time')->first()?->end_time->format('Y-m-d H:i')) }}">
                                </div>
                            </div>
                        </div>

                        @if($event->parent_id == null)
                            <div class="bg-light p-3 rounded border">
                                <h6 class="fw-bold mb-3">Recurrence Settings</h6>
                                <div class="form-check mb-2">
                                    <input class="form-check-input" type="radio" name="event_type" id="standard_event" value="2" {{ old('event_type', $event->recurrence_interval == null ? '2' : '1') == '2' ? 'checked' : '' }}>
                                    <label class="form-check-label" for="standard_event">One-time Event</label>
                                </div>

                                <div class="form-check mb-3">
                                    <input class="form-check-input" type="radio" name="event_type" id="is_recurring" value="1" {{ old('event_type', $event->recurrence_interval != null ? '1' : '2') == '1' ? 'checked' : '' }}>
                                    <label class="form-check-label" for="is_recurring">Recurring Event</label>
                                </div>
                                
                                <div id="recurringOptions" class="collapse {{ old('event_type', $event->recurrence_interval != null ? '1' : '2') == '1' ? 'show' : '' }} mt-3">
                                    <div class="row">
                                        <div class="col-md-6 mb-3">
                                            <label for="recurrence_unit" class="form-label small fw-bold text-uppercase">Frequency</label>
                                            <select name="recurrence_unit" id="recurrence_unit" class="form-control">
                                                <option value="0">None</option>
                                                @foreach (\App\Helpers\EventHelper::labels() as $val => $lab)
                                                    <option value="{{ $val }}" {{ old('recurrence_unit', $event->recurrence_unit) == $val ? 'selected' : '' }}>{{ $lab }}</option>
                                                @endforeach
                                            </select>
                                        </div>
                                        <div class="col-md-6 mb-3">
                                            <label for="recurrence_interval" class="form-label small fw-bold text-uppercase">Every X Interval</label>
                                            <input type="number" name="recurrence_interval" class="form-control" value="{{ old('recurrence_interval', $event->recurrence_interval) }}">
                                        </div>
                                        <div class="col-md-12">
                                            <label for="recurrence_end_date" class="form-label small fw-bold text-uppercase text-danger">Series Ends On</label>
                                            <input type="text" id="recurrence_end_date" name="recurrence_end_date" class="form-control" 
                                                value="{{ old('recurrence_end_date', $event->recurrence_end_date ? \Carbon\Carbon::parse($event->recurrence_end_date)->format('Y-m-d H:i') : '') }}">
                                        </div>
                                    </div>
                                </div>
                            </div>
                        @endif

                        <button type="submit" class="btn btn-success btn-lg mt-4 w-100 shadow-sm">
                            <i class="fas fa-save me-2"></i> Save Changes
                        </button>
                    </form>
                </div>
            </div>
        </div>

        {{-- Right Column: Upcoming Occurrences --}}
        <div class="col-xl-4 col-lg-5">
            <div class="card shadow mb-4">
                <div class="card-header bg-dark py-3 d-flex justify-content-between align-items-center">
                    <h6 class="m-0 fw-bold text-white">Upcoming Occurrences</h6>
                    {{-- Optional: Total Count Badge --}}
                    <span class="badge bg-secondary text-white">Future</span>
                </div>
                <div class="card-body p-0" style="max-height: 800px; overflow-y: auto;">
                    <div class="list-group list-group-flush">
                        @php
                            // Fetch both active and soft-deleted instances from today onwards
                            $allInstances = $event->instances()
                                ->withTrashed()
                                ->where('start_time', '>=', now()->startOfDay())
                                ->get()
                                ->sortBy('start_time');
                        @endphp

                        @forelse($allInstances as $instance)
                            <div class="list-group-item {{ $instance->trashed() ? 'bg-light' : ($instance->start_time->isToday() ? 'bg-gray-100' : '') }}" 
                                style="{{ $instance->trashed() ? 'opacity: 0.7;' : '' }}">
                                
                                <div class="d-flex w-100 justify-content-between align-items-center">
                                    <h6 class="mb-1 fw-bold {{ $instance->trashed() ? 'text-muted text-decoration-line-through' : 'text-primary' }}">
                                        {{ $instance->start_time->format('D, M j, Y') }}
                                    </h6>
                                    
                                    <div class="d-flex align-items-center">
                                        @if($instance->trashed())
                                            {{-- Restore Button --}}
                                            <form action="{{ route('event-instances.restore', $instance->id) }}" method="POST">
                                                @csrf
                                                <button type="submit" class="btn btn-sm btn-outline-success py-0" title="Restore this date">
                                                    <i class="fas fa-undo-alt"></i> Restore
                                                </button>
                                            </form>
                                        @else
                                            @if($instance->start_time->isToday())
                                                <span class="badge bg-success text-white me-2">Today</span>
                                            @endif

                                            {{-- Delete Button --}}
                                            <form action="{{ route('event-instances.destroy', $instance->id) }}" method="POST" onsubmit="return confirm('Remove this specific date?');">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="btn btn-link text-danger p-0 shadow-none">
                                                    <i class="fas fa-trash-alt"></i>
                                                </button>
                                            </form>
                                        @endif
                                    </div>
                                </div>
                                
                                <p class="mb-1 small {{ $instance->trashed() ? 'text-muted' : 'text-dark' }}">
                                    <i class="far fa-clock me-1 opacity-50"></i>
                                    {{ $instance->start_time->format('H:i') }} — {{ $instance->end_time->format('H:i') }}
                                    @if($instance->trashed())
                                        <span class="ms-2 badge bg-light text-muted border small">Removed</span>
                                    @endif
                                </p>
                            </div>
                        @empty
                            <div class="p-5 text-center">
                                <i class="fas fa-calendar-xmark fa-3x text-light mb-3"></i>
                                <p class="text-muted">No upcoming instances found.</p>
                            </div>
                        @endforelse
                    </div>
                </div>
                
                @if($allInstances->count() > 0)
                    <div class="card-footer bg-white border-top text-center py-2">
                        <small class="text-muted fw-bold">
                            {{ $allInstances->whereNull('deleted_at')->count() }} Active / {{ $allInstances->whereNotNull('deleted_at')->count() }} Removed
                        </small>
                    </div>
                @endif
            </div>
        </div>
    </div>
</div>
@endsection

@section('js')
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/simplemde/latest/simplemde.min.css">
    <script src="https://cdn.jsdelivr.net/simplemde/latest/simplemde.min.js"></script>
    <script type="module">
        document.addEventListener("DOMContentLoaded", function() {

            // Always pull the EARLIEST instance to ensure the form is consistent
            const startVal = "{{ old('start_date', $event->instances->sortBy('start_time')->first()?->start_time->format('Y-m-d H:i') ?? '') }}";
            const endVal = "{{ old('end_date', $event->instances->sortBy('start_time')->first()?->end_time->format('Y-m-d H:i') ?? '') }}";
            const recEndVal = "{{ old('recurrence_end_date', $event->recurrence_end_date ? \Carbon\Carbon::parse($event->recurrence_end_date)->format('Y-m-d H:i') : '') }}";

            // Support editing past events by allowing minDate to be the current value or today
            const minDateLimit = (startVal && new Date(startVal) < new Date()) ? startVal : "today";

            const startPicker = flatpickr('#start_date', {
                minDate: minDateLimit,
                dateFormat: "Y-m-d H:i",
                altFormat: "d-m-Y H:i",
                altInput: true,
                enableTime: true,
                time_24hr: true,
                defaultDate: startVal,
                onChange: function(selectedDates) {
                    const newMin = selectedDates[0] || "today";
                    endPicker.set('minDate', newMin);
                    if(recPicker) recPicker.set('minDate', newMin);
                }
            });

            const endPicker = flatpickr('#end_date', {
                minDate: minDateLimit,
                dateFormat: "Y-m-d H:i",
                altFormat: "d-m-Y H:i",
                altInput: true,
                enableTime: true,
                time_24hr: true,
                defaultDate: endVal
            });

            let recPicker = null;
            if(document.querySelector('#recurrence_end_date')){
                recPicker = flatpickr('#recurrence_end_date', {
                    minDate: minDateLimit,
                    dateFormat: "Y-m-d H:i",
                    altFormat: "d-m-Y H:i",
                    altInput: true,
                    enableTime: true,
                    time_24hr: true,
                    defaultDate: recEndVal
                });
            }

            // SimpleMDE
            new SimpleMDE({ element: document.getElementById('long_description'), status: false });
            
            // Toggle Recurrence Options
            const rRadio = document.getElementById('is_recurring');
            const sRadio = document.getElementById('standard_event');
            const rDiv = document.getElementById('recurringOptions');
            
            function toggle() {
                if (rRadio?.checked) {
                    rDiv.classList.add('show');
                } else {
                    rDiv?.classList.remove('show');
                }
            }
            rRadio?.addEventListener('change', toggle);
            sRadio?.addEventListener('change', toggle);
            
            // File input label handler
            if (typeof bsCustomFileInput !== 'undefined') bsCustomFileInput.init();
        });
    </script>
@endsection