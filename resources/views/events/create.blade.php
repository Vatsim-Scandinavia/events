@extends('layouts.app')
@section('title', 'Create Event')
@section('content')
    <div class="row">
        <div class="col-xl-6 col-md-12 mb-12">
            <div class="card shadow mb-4">
                <div class="card-header bg-primary py-3 d-flex flex-row align-items-center">
                    <h6 class="m-0 fw-bold text-white">User input</h6> 
                </div>
                <div class="card-body">
                    <form action="{{ route('events.store') }}" method="post" enctype="multipart/form-data">
                        @csrf
                        <div class="container-fluid">
                            <div class="row pt-2">
                                <div class="col-xl-12 col-md-12 mb-12">
                                    <div class="form-group mb-4">
                                        <label for="event" class="form-label my-1 me-2">Event Title</label>
                                        <input type="text" name="title" id="title" class="form-control @error('title') is-invalid @enderror" value="{{ old('title') }}" required>
                                        @error('title')
                                            <span class="text-danger">{{ $errors->first('title') }}</span>
                                        @enderror
                                    </div>

                                    <div class="form-group mb-4">
                                        <label for="calendar" class="form-label my-1 me-2">Calendar</label>
                                        <select name="calendar_id" id="calendar" class="form-control my-1 me-sm-2 @error('calendar_id') is-invalid @enderror" required>
                                            <option disabled selected>Select Calendar</option>
                                            @foreach ($calendars as $calendar)
                                                @can('view', $calendar)
                                                    <option value="{{ $calendar->id }}" {{ old('calendar_id') == $calendar->id ? 'selected' : '' }}>{{ $calendar->name }} ({{ $calendar->public == 1 ? 'Public' : 'Private' }})</option>
                                                @endcan
                                            @endforeach
                                        </select>
                                        @error('calendar_id')
                                            <span class="text-danger">{{ $errors->first('calendar_id') }}</span>
                                        @enderror
                                    </div>

                                    <div class="form-group mb-4">
                                        <label for="short_description" class="form-label my-1 me-2">Short Description (max 280 characters)</label>
                                        <textarea class="form-control @error('short_description') is-invalid @enderror" name="short_description" id="short_description" rows="8">{{ old('short_description') }}</textarea>
                                        @error('short_description')
                                            <span class="text-danger">{{ $errors->first('short_description') }}</span>
                                        @enderror
                                    </div>

                                    <div class="form-group mb-4">
                                        <label for="long_description" class="form-label my-1 me-2">Event Description</label>
                                        <textarea class="form-control @error('long_description') is-invalid @enderror" name="long_description" id="long_description" rows="8">{{ old('long_description') }}</textarea>
                                        @error('long_description')
                                            <span class="text-danger">{{ $errors->first('long_description') }}</span>
                                        @enderror
                                    </div>

                                    <div class="form-group mb-4">
                                        <label for="customFile" class="form-label my-1 me-2">Image upload</label>
                                        <div class="custom-file">
                                            <input type="file" class="custom-file-input @error('image') is-invalid @enderror" id="customFile" name="image" accept="image/jpg, image/jpeg, image/png" />
                                            <label class="custom-file-label" for="customFile">Choose file</label>
                                            @error('image')
                                                <span class="invalid-feedback" role="alert">
                                                    <strong>{{ $message }}</strong>
                                                </span>
                                            @enderror
                                        </div>
                                    </div>

                                    <div class="row mb-4">
                                        <div class="col-md-6">
                                            <div class="form-group">
                                                <label for="start_date" class="form-label my-1 me-2">Start Date & Time (Zulu)</label>
                                                <input type="text" name="start_date" id="start_date" class="datepicker form-control @error('start_date') is-invalid @enderror">
                                                @error('start_date')
                                                    <span class="text-danger">{{ $errors->first('start_date') }}</span>
                                                @enderror
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="form-group">
                                                <label for="end_date" class="form-label my-1 me-2">End Date & Time (Zulu)</label>
                                                <input type="text" name="end_date" id="end_date" class="datepicker form-control @error('end_date') is-invalid @enderror">
                                                @error('end_date')
                                                    <span class="text-danger">{{ $errors->first('end_date') }}</span>
                                                @enderror
                                            </div>
                                        </div>
                                    </div>

                                    <hr class="my-4">

                                    <div class="form-check">
                                        <input class="form-check-input" type="radio" name="event_type" id="standard_event" value="0" {{ old('event_type') == 0 ? 'checked' : '' }}>
                                        <label class="form-check-label" for="standard_event">
                                          Standard Event
                                        </label>
                                    </div>

                                    <div class="form-check mb-4">
                                        <input class="form-check-input" type="radio" name="event_type" id="is_recurring" value="1" data-toggle="collapse" data-target="#recurringOptions" aria-expanded="false" aria-controls="recurringOptions" {{ old('event_type') == 1 ? 'checked' : '' }}>
                                        <label class="form-check-label" for="is_recurring">
                                            Recurring Event
                                        </label>
                                    </div>
                                    
                                    <div id="recurringOptions" class="collapse col-xs-12 col-sm-12 col-md-12 mb-2 {{ old('event_type') == 1 ? 'show' : '' }}">
                                        <div class="col-xs-12 col-sm-12 col-md-12 mb-2">
                                            <div class="form-group">
                                                <label for="recurrence_unit" class="form-label my-1 me-2">Recurrence Type</label>
                                                <select id="recurrence_unit" name="recurrence_unit" class="form-control my-1 me-sm-2 @error('recurrence_unit') is-invalid @enderror">
                                                    <option value="0" selected>None</option>
                                                    @foreach (\App\Helpers\EventHelper::labels() as $value => $label)
                                                        <option value="{{ $value }}" {{ old('recurrence_unit') == $value ? 'selected' : '' }}>{{ $label }}</option>
                                                    @endforeach
                                                </select>
                                                @error('recurrence_unit')
                                                    <span class="text-danger">{{ $errors->first('recurrence_unit') }}</span>
                                                @enderror
                                            </div>
                                        </div>
                                        <div class="col-xs-12 col-sm-12 col-md-12 mb-2">
                                            <div class="form-group">
                                                <label for="recurrence_interval">Recurrence Interval</label>
                                                <input type="number" id="recurrence_interval" name="recurrence_interval" class="form-control" value="{{ old('recurrence_interval') }}" placeholder="eg. 2 for every second day">
                                            </div>
                                        </div>
                                        <div class="col-xs-12 col-sm-12 col-md-12 mb-2">
                                            <div class="form-group">
                                                <label for="recurrence_end_date">Recurrence end date</label>
                                                <input type="date" id="recurrence_end_date" name="recurrence_end_date" class="datepicker form-control" value="{{ old('recurrence_end_date') }}">
                                            </div>
                                        </div>
                                    </div>

                                </div>
                                <button type="submit" id="submit-btn" class="btn btn-success">Save Event</button>
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
        });
    </script>
@endsection