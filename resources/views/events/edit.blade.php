@extends('layouts.auth.app')
@section('title', 'Edit Event')
@section('content')
    <div class="row">
        <div class="col-xl-12 col-md-12 mb-12">
            <div class="card shadow mb-4">
                <div class="card-header bg-primary py-3 d-flex flex-row align-items-center justify-content-center">
                    <h6 class="m-0 fw-bold text-white">User input</h6> 
                </div>
                <div class="card-body">
                    <form action="{{ route('events.update', $event) }}" method="post" enctype="multipart/form-data">
                        @csrf
                        @method('PATCH')
                        <div class="container-fluid">
                            <div class="row pt-2">
                                <div class="col-xs-12 col-sm-12 col-md-12 mb-2">
                                    <div class="form-group">
                                        <label for="event" class="form-label my-1 me-2">Event Title<i class="fas fa-xs fa-asterisk" style="color: red;"></i></label>
                                        <input type="text" name="title" id="title" class="form-control @error('title') is-invalid @enderror" value="{{ $event->title }}" required>
                                        @error('title')
                                            <span class="text-danger">{{ $errors->first('title') }}</span>
                                        @enderror
                                    </div>
                                </div>
                                <div class="col-xs-12 col-sm-12 col-md-12 mb-2">
                                    <div class="form-group">
                                        <label for="calendar" class="form-label my-1 me-2">Calendar <i class="fas fa-xs fa-asterisk" style="color: red;"></i></label>
                                        <select name="calendar_id" id="calendar" class="form-control my-1 me-sm-2 @error('calendar') is-invalid @enderror" required>
                                            <option disabled>Select Calendar</option>
                                            @foreach ($calendars as $calendar)
                                                @can('view', $calendar)
                                                    <option value="{{ $calendar->id }}" {{ $event->calendar_id == $calendar->id ? 'selected' : '' }}>{{ $calendar->name }}</option>
                                                @endcan
                                            @endforeach
                                        </select>
                                        @error('calendar')
                                            <span class="text-danger">{{ $errors->first('calendar') }}</span>
                                        @enderror
                                    </div>
                                </div>
                                <div class="col-xs-12 col-sm-12 col-md-12 mb-2">
                                    <div class="form-group">
                                        <label for="area" class="form-label my-1 me-2">FIR <i class="fas fa-xs fa-asterisk" style="color: red;"></i></label>
                                        <select name="area" id="area" class="form-control my-1 me-sm-2 @error('area') is-invalid @enderror" required>
                                            <option disabled>Select FIR</option>
                                            @foreach ($areas as $area)
                                                <option value="{{ $area->id }}" {{ $event->area_id == $area->id ? 'selected' : '' }}>{{ $area->name }}</option>
                                            @endforeach
                                        </select>
                                        @error('area')
                                            <span class="text-danger">{{ $errors->first('area') }}</span>
                                        @enderror
                                    </div>
                                </div>
                                <div class="col-xs-12 col-sm-12 col-md-12 mb-2">
                                    <div class="form-group">
                                        <label for="description" class="form-label my-1 me-2">Description</label>
                                        <textarea class="form-control @error('description') is-invalid @enderror" name="description" id="description" rows="8">{{ $event->description }}</textarea>
                                        @error('description')
                                            <span class="text-danger">{{ $errors->first('description') }}</span>
                                        @enderror
                                    </div>
                                </div>
                                <div class="col-xs-12 col-sm-12 col-md-12 mb-2">
                                    <div class="form-group">
                                        <label for="customFile" class="form-label my-1 me-2">Image upload</label>
                                        <div class="custom-file">
                                            <input type="file" class="custom-file-input @error('image') is-invalid @enderror" id="customFile" name="image">
                                            <label class="custom-file-label" for="customFile">Choose file</label>
                                            @error('image')
                                                <span class="invalid-feedback" role="alert">
                                                    <strong>{{ $message }}</strong>
                                                </span>
                                            @enderror
                                        </div>
                                        @if ($event->image)
                                            <img src="{{ asset('storage/images/' . $event->image) }}" alt="Event Image" class="img-thumbnail mt-2" width="200">
                                        @endif
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="start_date" class="form-label my-1 me-2">Start Date & Time <i class="fas fa-xs fa-asterisk" style="color: red;"></i></label>
                                        <input type="text" name="start_date" id="start_date" class="datepicker form-control @error('start_date') is-invalid @enderror" value="{{ $event->start_date }}">
                                        @error('start_date')
                                            <span class="text-danger">{{ $errors->first('start_date') }}</span>
                                        @enderror
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="end_date" class="form-label my-1 me-2">End Date & Time <i class="fas fa-xs fa-asterisk" style="color: red;"></i></label>
                                        <input type="text" name="end_date" id="end_date" class="datepicker form-control @error('end_date') is-invalid @enderror" value="{{ $event->end_date }}">
                                        @error('end_date')
                                            <span class="text-danger">{{ $errors->first('end_date') }}</span>
                                        @enderror
                                    </div>
                                </div>
                                <div class="col">
                                    <hr class="my-4">
                                </div>
                                <div class="col-xs-12 col-sm-12 col-md-12 mb-2">
                                    <div class="form-check">
                                        <input type="hidden" name="event_type" value="0">
                                        <input class="form-check-input" type="radio" name="event_type" id="is_recurring" value="1" data-toggle="collapse" data-target="#recurringOptions" aria-expanded="false" aria-controls="recurringOptions" {{ $event->recurrence_interval != null && $event->recurrence_unit != null ? 'checked' : '' }}>
                                        <label class="form-check-label" for="is_recurring">
                                            Recurring Event
                                        </label>
                                      </div>
                                </div>
                                <div class="col-xs-12 col-sm-12 col-md-12 mb-2">
                                    <div class="form-check">
                                        <input class="form-check-input" type="radio" name="event_type" id="is_full_day" value="2" {{ $event->is_full_day == 1 ? 'checked' : '' }}>
                                        <label class="form-check-label" for="is_full_day">
                                          Full day event
                                        </label>
                                    </div>
                                </div>
                                <div id="recurringOptions" class="collapse col-xs-12 col-sm-12 col-md-12 mb-2">
                                    <div class="col-xs-12 col-sm-12 col-md-12 mb-2">
                                        <div class="form-group">
                                            <label for="recurrence_unit" class="form-label my-1 me-2">Recurrence Type</label>
                                            <select id="recurrence_unit" name="recurrence_unit" class="form-control my-1 me-sm-2 @error('recurrence_unit') is-invalid @enderror">
                                                <option value="0" selected>None</option>
                                                @foreach (\App\Helpers\EventHelper::labels() as $value => $label)
                                                    <option value="{{ $value }}" {{ $event->recurrence_unit == $value ? 'selected' : '' }}>{{ $label }}</option>
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
                                            <input type="number" id="recurrence_interval" name="recurrence_interval" class="form-control" value="{{ $event->recurrence_interval }}" placeholder="eg. 2 for every second day">
                                        </div>
                                    </div>
                                    <div class="col-xs-12 col-sm-12 col-md-12 mb-2">
                                        <div class="form-group">
                                            <label for="recurrence_end_date">Recurrence end date</label>
                                            <input type="date" id="recurrence_end_date" name="recurrence_end_date" class="datepicker form-control" value="{{ $event->recurrence_end_date }}">
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
                element: document.getElementById('description'),
                status: false,
                toolbar: ["bold", "italic", "heading-3", "|", "quote", "unordered-list", "ordered-list", "|", "link", "preview", "side-by-side", "fullscreen", "|", "guide"],
                insertTexts: {
                    link: ["[","](link)"],
                }
            });

            var defaultDate1 = "{{ old('start_date') }}";
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
                }
            });

            var defaultDate2 = "{{ old('end_date') }}";
            document.querySelector('#end_date').flatpickr({
                minDate: document.querySelector('#start_date').value,
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

            var defaultDate3 = "{{ old('recurrence_end_date') }}";
            document.querySelector('#recurrence_end_date').flatpickr({
                minDate: document.querySelector('#start_date').value,
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

            var flatpickrInputs = document.querySelectorAll('.datepicker');
            flatpickrInputs.forEach(function(input) {
                input.addEventListener('focus', function() {
                    input.blur();
                });
                input.readOnly = false;
            });

            const radios = document.querySelectorAll('input[type="radio"]');
            let lastChecked = null;

            radios.forEach(radio => {
                radio.addEventListener('click', function(event) {
                    if (this === lastChecked) {
                        this.checked = false;
                        lastChecked = null;
                    } else {
                        lastChecked = this;
                    }
                });
            });

            var isRecurringRadio = document.getElementById('is_recurring');
            var isFullDayRadio = document.getElementById('is_full_day');
            var recurringOptionsCollapse = document.getElementById('recurringOptions');

            // Function to show/hide the recurring options collapse
            function toggleRecurringOptions() {
                if (isRecurringRadio.checked) {
                    recurringOptionsCollapse.classList.add('show');
                } else {
                    recurringOptionsCollapse.classList.remove('show');
                }
            }

            // Initial state based on selected radio
            toggleRecurringOptions();

            // Event listeners for radio button changes
            isRecurringRadio.addEventListener('change', toggleRecurringOptions);
            isFullDayRadio.addEventListener('change', function() {
                // Hide the recurring options collapse when Full day event is selected
                recurringOptionsCollapse.classList.remove('show');
            });
        });
    </script>
@endsection