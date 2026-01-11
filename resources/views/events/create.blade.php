@extends('layouts.app')
@section('title', 'Create Event')
@section('content')
<div class="row">
    <div class="col-xl-6 col-md-12 mb-12">
        <div class="card shadow mb-4">
            <div class="card-header bg-primary py-3 d-flex flex-row align-items-center">
                <h6 class="m-0 fw-bold text-white">Create New Event</h6>
            </div>
            <div class="card-body">
                <form action="{{ route('events.store') }}" method="post" enctype="multipart/form-data">
                    @csrf
                    <div class="container-fluid">
                        <div class="row pt-2">
                            <div class="col-xl-12 col-md-12 mb-12">
                                
                                {{-- Event Title --}}
                                <div class="form-group mb-4">
                                    <label for="title" class="form-label fw-bold">Event Title</label>
                                    <input type="text" name="title" id="title" class="form-control @error('title') is-invalid @enderror" value="{{ old('title') }}" required>
                                    @error('title')
                                        <span class="text-danger small">{{ $message }}</span>
                                    @enderror
                                </div>

                                {{-- Calendar Selection --}}
                                <div class="form-group mb-4">
                                    <label for="calendar" class="form-label fw-bold">Calendar</label>
                                    <select name="calendar_id" id="calendar" class="form-control @error('calendar_id') is-invalid @enderror" required>
                                        <option disabled selected>Select Calendar</option>
                                        @foreach ($calendars as $calendar)
                                            @can('view', $calendar)
                                                <option value="{{ $calendar->id }}" {{ old('calendar_id') == $calendar->id ? 'selected' : '' }}>
                                                    {{ $calendar->name }} ({{ $calendar->public == 1 ? 'Public' : 'Private' }})
                                                </option>
                                            @endcan
                                        @endforeach
                                    </select>
                                </div>

                                {{-- Short Description --}}
                                <div class="form-group mb-4">
                                    <label for="short_description" class="form-label fw-bold">Short Description (max 280 characters)</label>
                                    <textarea class="form-control @error('short_description') is-invalid @enderror" name="short_description" id="short_description" rows="3">{{ old('short_description') }}</textarea>
                                    <small id="characterCount" class="form-text text-muted">0/280 characters</small>
                                </div>

                                {{-- Long Description --}}
                                <div class="form-group mb-4">
                                    <label for="long_description" class="form-label fw-bold">Event Description</label>
                                    <textarea class="form-control @error('long_description') is-invalid @enderror" name="long_description" id="long_description" rows="8">{{ old('long_description') }}</textarea>
                                </div>

                                {{-- Image Upload --}}
                                <div class="form-group mb-4">
                                    <label for="customFile" class="form-label fw-bold">Banner Image</label>
                                    <div class="custom-file">
                                        <input type="file" class="custom-file-input @error('image') is-invalid @enderror" id="customFile" name="image" accept="image/*" />
                                        <label class="custom-file-label" for="customFile">Choose file</label>
                                    </div>
                                </div>

                                {{-- Date & Time Row --}}
                                <div class="row mb-4">
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label for="start_date" class="form-label fw-bold text-primary">Start Date & Time</label>
                                            <input type="text" name="start_date" id="start_date" class="datepicker form-control border-primary @error('start_date') is-invalid @enderror" value="{{ old('start_date') }}">
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label for="end_date" class="form-label fw-bold text-primary">End Date & Time</label>
                                            <input type="text" name="end_date" id="end_date" class="datepicker form-control border-primary @error('end_date') is-invalid @enderror" value="{{ old('end_date') }}">
                                        </div>
                                    </div>
                                </div>

                                {{-- New Styled Recurrence Section --}}
                                <div class="bg-light p-3 rounded border mb-4">
                                    <h6 class="fw-bold mb-3">Recurrence Settings</h6>
                                    
                                    <div class="form-check mb-2">
                                        <input class="form-check-input" type="radio" name="event_type" id="standard_event" value="0" {{ old('event_type', '0') == '0' ? 'checked' : '' }}>
                                        <label class="form-check-label" for="standard_event">One-time Event</label>
                                    </div>

                                    <div class="form-check mb-3">
                                        <input class="form-check-input" type="radio" name="event_type" id="is_recurring" value="1" {{ old('event_type') == '1' ? 'checked' : '' }}>
                                        <label class="form-check-label" for="is_recurring">Recurring Event</label>
                                    </div>
                                    
                                    <div id="recurringOptions" class="collapse {{ old('event_type') == '1' ? 'show' : '' }} mt-3">
                                        <div class="row">
                                            <div class="col-md-6 mb-3">
                                                <label for="recurrence_unit" class="form-label small fw-bold text-uppercase">Frequency</label>
                                                <select id="recurrence_unit" name="recurrence_unit" class="form-control">
                                                    <option value="0">None</option>
                                                    @foreach (\App\Helpers\EventHelper::labels() as $value => $label)
                                                        <option value="{{ $value }}" {{ old('recurrence_unit') == $value ? 'selected' : '' }}>{{ $label }}</option>
                                                    @endforeach
                                                </select>
                                            </div>
                                            <div class="col-md-6 mb-3">
                                                <label for="recurrence_interval" class="form-label small fw-bold text-uppercase">Every X Interval</label>
                                                <input type="number" id="recurrence_interval" name="recurrence_interval" class="form-control" value="{{ old('recurrence_interval') }}" placeholder="eg. 2">
                                            </div>
                                            <div class="col-md-12">
                                                <label for="recurrence_end_date" class="form-label small fw-bold text-uppercase text-danger">Series Ends On</label>
                                                <input type="text" id="recurrence_end_date" name="recurrence_end_date" class="datepicker form-control" value="{{ old('recurrence_end_date') }}">
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <button type="submit" id="submit-btn" class="btn btn-success btn-lg w-100 shadow-sm">
                                    <i class="fas fa-save me-2"></i> Save Event
                                </button>
                            </div>
                        </div>
                    </div>
                </form>
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
            var simplemde1 = new SimpleMDE({
                element: document.getElementById('long_description'),
                status: false,
                toolbar: ["bold", "italic", "heading-3", "|", "quote", "unordered-list", "ordered-list", "|", "link", "preview", "side-by-side", "fullscreen", "|", "guide"],
                insertTexts: {
                    link: ["[","](link)"],
                }
            });

            var defaultDate1 = "{{ old('start_date') }}";
            var defaultDate2 = "{{ old('end_date') }}";
            var defaultDate3 = "{{ old('recurrence_end_date') }}";

            document.querySelector('#start_date').flatpickr({
                minDate: "{!! date('Y-m-d H:i') !!}",
                dateFormat: "Y-m-d H:i",
                altFormat: "d-m-Y H:i",
                altInput: true,
                enableTime: true,
                time_24hr: true,
                defaultDate: defaultDate1,
                locale: {
                    firstDayOfWeek: 1
                },
                onChange: function(selectedDates, dateStr, instance) {
                    if (selectedDates.length > 0) {
                        var startDate = selectedDates[0];

                        // Update end_date picker
                        var endDatePicker = document.querySelector('#end_date')._flatpickr;
                        endDatePicker.set('minDate', startDate);

                        // Update recurrence_end_date picker
                        var recurrenceEndDatePicker = document.querySelector('#recurrence_end_date')._flatpickr;
                        recurrenceEndDatePicker.set('minDate', startDate);

                        // Calculate the maximum date for recurrence_end_date (6 months after the start date)
                        var maxRecurrenceEndDate = new Date(startDate);
                        maxRecurrenceEndDate.setMonth(maxRecurrenceEndDate.getMonth() + 6);
                        recurrenceEndDatePicker.set('maxDate', maxRecurrenceEndDate);
                    }
                }
            });

            var endDatePicker = document.querySelector('#end_date').flatpickr({
                minDate: defaultDate1 || "{!! date('Y-m-d H:i') !!}",
                dateFormat: "Y-m-d H:i",
                altFormat: "d-m-Y H:i",
                altInput: true,
                enableTime: true,
                time_24hr: true,
                defaultDate: defaultDate2,
                locale: {
                    firstDayOfWeek: 1
                }
            });

            var recurrenceEndDatePicker = document.querySelector('#recurrence_end_date').flatpickr({
                minDate: defaultDate1 || "{!! date('Y-m-d H:i') !!}",
                dateFormat: "Y-m-d H:i",
                altFormat: "d-m-Y H:i",
                altInput: true,
                enableTime: true,
                time_24hr: true,
                defaultDate: defaultDate3,
                locale: {
                    firstDayOfWeek: 1
                }
            });

            // Initial setup to ensure correct restrictions
            if (defaultDate1) {
                var startDate = new Date(defaultDate1);

                // Update end_date picker
                endDatePicker.set('minDate', startDate);

                // Update recurrence_end_date picker
                recurrenceEndDatePicker.set('minDate', startDate);

                // Calculate the maximum date for recurrence_end_date (6 months after the start date)
                var maxRecurrenceEndDate = new Date(startDate);
                maxRecurrenceEndDate.setMonth(maxRecurrenceEndDate.getMonth() + 6);
                recurrenceEndDatePicker.set('maxDate', maxRecurrenceEndDate);
            }

            var flatpickrInputs = document.querySelectorAll('.datepicker');
            flatpickrInputs.forEach(function(input) {
                input.addEventListener('focus', function() {
                    input.blur();
                });
                input.readOnly = false;
            });

            var isRecurringRadio = document.getElementById('is_recurring');
            var isStandardRadio = document.getElementById('standard_event');
            var recurringOptionsCollapse = document.getElementById('recurringOptions');

            // Function to show/hide the recurring options collapse
            function toggleRecurringOptions() {
                if (isRecurringRadio.checked) {
                    recurringOptionsCollapse.classList.add('show');
                } else {
                    recurringOptionsCollapse.classList.remove('show');
                }
            }

            // // Initial state based on selected radio
            // toggleRecurringOptions();

            // Event listeners for radio button changes
            isRecurringRadio.addEventListener('change', toggleRecurringOptions);
            isStandardRadio.addEventListener('change', function() {
                // Hide the recurring options collapse when Full day event is selected
                recurringOptionsCollapse.classList.remove('show');
            });

            // Initialize custom file input
            bsCustomFileInput.init();

            /* Character counter */
            function updateCharacterCount() {
                const textarea = document.getElementById('short_description');
                const characterCount = document.getElementById('characterCount');
                const count = textarea.value.length;

                characterCount.textContent = `${count}/280 characters`;

                if (count > 280) {
                    characterCount.classList.remove('text-muted');
                    characterCount.classList.add('text-danger');
                } else {
                    characterCount.classList.remove('text-danger');
                    characterCount.classList.add('text-muted');
                }
            }

            // Add event listener for 'input' event
            document.getElementById('short_description').addEventListener('input', updateCharacterCount);

            // Initialize the character count on page load in case there's already text in the textarea
            document.addEventListener('DOMContentLoaded', updateCharacterCount);
        });
    </script>
@endsection