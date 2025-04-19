@extends('layouts.app')

@section('title', 'Create Staffing')
@section('content')
    <div class="row">
        <div class="col-xl-12 col-md-12 mb-12">
            <div class="card shadow mb-4">
                <div class="card-header bg-primary py-3 d-flex flex-row align-items-center justify-content-center">
                    <h6 class="m-0 fw-bold text-white">User input</h6> 
                </div>
                <div class="card-body">
                    <form action="{{ route('staffings.store') }}" method="post">
                        @csrf
                        <div class="container-fluid">
                            <div class="row pt-2">
                                <div class="col-xs-12 col-sm-12 col-md-12 mb-2">
                                    <div class="form-group">
                                        <label for="event" class="form-label my-1 me-2">Event <i class="fas fa-xs fa-asterisk" style="color: red;"></i></label>
                                        <select name="event" id="event" class="form-control my-1 me-sm-2" required>
                                            <option disabled selected>Select Event</option>
                                            @if (!$events->isEmpty())
                                                @foreach ($events as $event)
                                                    <option value="{{ $event->id }}">{{ $event->title }}</option>
                                                @endforeach
                                            @else
                                                <option disabled>No events available</option>
                                            @endif
                                        </select>
                                    </div>
                                </div>
                                <div class="col-xs-12 col-sm-12 col-md-12 mb-2">
                                    <div class="form-group">
                                        <label for="description" class="form-label my-1 me-2">Description <i class="fas fa-xs fa-asterisk" style="color: red;"></i></label>
                                        <textarea class="form-control @error('description') is-invalid @enderror" name="description" id="description" rows="8">{{ old('description') }}</textarea>
                                        @error('description')
                                            <span class="text-danger">{{ $errors->first('description') }}</span>
                                        @enderror
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="section_1_title" class="form-label my-1 me-2">Section 1 Title <i class="fas fa-xs fa-asterisk" style="color: red;"></i></label>
                                        <textarea class="form-control @error('section_1_title') is-invalid @enderror" name="section_1_title" id="section_1_title">{{ old('section_1_title') }}</textarea>
                                        @error('section_1_title')
                                            <span class="text-danger">{{ $errors->first('section_1_title') }}</span>
                                        @enderror
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="section_2_title" class="form-label my-1 me-2">Section 2 Title</label>
                                        <textarea class="form-control @error('section_2_title') is-invalid @enderror" name="section_2_title" id="section_2_title">{{ old('section_2_title') }}</textarea>
                                        @error('section_2_title')
                                            <span class="text-danger">{{ $errors->first('section_2_title') }}</span>
                                        @enderror
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="section_3_title" class="form-label my-1 me-2">Section 3 Title</label>
                                        <textarea class="form-control @error('section_3_title') is-invalid @enderror" name="section_3_title" id="section_3_title">{{ old('section_3_title') }}</textarea>
                                        @error('section_3_title')
                                            <span class="text-danger">{{ $errors->first('section_3_title') }}</span>
                                        @enderror
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="section_4_title" class="form-label my-1 me-2">Section 4 Title</label>
                                        <textarea class="form-control @error('section_4_title') is-invalid @enderror" name="section_4_title" id="section_4_title">{{ old('section_4_title') }}</textarea>
                                        @error('section_4_title')
                                            <span class="text-danger">{{ $errors->first('section_4_title') }}</span>
                                        @enderror
                                    </div>
                                </div>
                                <div class="col">
                                    <hr class="my-4">
                                </div>
                                <div class="col-xs-12 col-sm-12 col-md-12 mb-2">
                                    <div class="form-group">
                                        <label for="channel_id" class="form-label my-1 me-2">Discord Channels <i class="fas fa-xs fa-asterisk" style="color: red;"></i></label>
                                        <select name="channel_id" id="channel_id" class="form-control my-1 me-sm-2" required>
                                            <option disabled selected>Select Discord Channel</option>
                                            @if (!empty($channels))
                                                @foreach ($channels as $channel)
                                                    <option value="{{ $channel['id']}}">#{{ $channel['name'] }}</option>
                                                @endforeach
                                            @else
                                                <option disabled>No channels available</option>
                                            @endif
                                        </select>
                                    </div>
                                </div>
                                <div class="col-xs-12 col-sm-12 col-md-12 mb-2">
                                    <div class="form-group">
                                        <label for="positions" class="form-label my-1 me-2">Positions <i class="fas fa-xs fa-asterisk" style="color: red;"></i></label>
                                        <div id="positions-container"></div>
                                        <button type="button" class="btn btn-primary mt-2" id="add-position">Add Position</button>
                                    </div>
                                </div>
                                <button type="submit" id="submit-btn" class="btn btn-success">Save Staffing</button>
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
        $(document).ready(function() {
            const editorFields = [ "description", "section_1_title", "section_2_title", "section_3_title", "section_4_title" ];
            editorFields.forEach(id => {
                new SimpleMDE({
                    element: document.getElementById(id),
                    status: false,
                    toolbar: ["bold", "italic", "heading-3", "|", "quote", "unordered-list", "ordered-list", "|", "link", "preview", "side-by-side", "fullscreen", "|", "guide"],
                    insertTexts: {
                        link: ["[","](link)"],
                    }
                });
            });

            let positionsContainer = document.getElementById("positions-container");
            let addPositionBtn = document.getElementById("add-position");
            let positionsData = @json($positions);

            function setupPositionListeners(positionDiv)
            {
                let callsignSelect = positionDiv.querySelector(".callsign-select");
                let callsignInput = positionDiv.querySelector(".callsign-input");
                let localBookingCheckbox = positionDiv.querySelector(".local-booking");
                let removeBtn = positionDiv.querySelector(".remove-position");

                if (localBookingCheckbox) {
                    localBookingCheckbox.addEventListener("change", function() {
                        if (this.checked) {
                            callsignSelect.classList.add("d-none");
                            callsignSelect.disabled = true;
                            callsignInput.classList.remove("d-none");
                            callsignInput.disabled = false;
                            callsignInput.value = "";
                        } else {
                            callsignInput.classList.add("d-none");
                            callsignInput.disabled = true;
                            callsignInput.value = "";
                            callsignSelect.classList.remove("d-none");
                            callsignSelect.disabled = false;
                        }
                    });
                }

                if (removeBtn) {
                    removeBtn.addEventListener("click", function() {
                        positionDiv.remove();
                    });
                }
            }

            function createPositionField() {
                let positionIndex = document.querySelectorAll(".position-entry").length;
                let positionDiv = document.createElement("div");
                positionDiv.classList.add("position-entry", "mb-3", "p-2", "border", "rounded");

                positionDiv.innerHTML = `
                    <div class="row">
                        <div class="col-md-4">
                            <div class="form-group">
                                <label class="form-label my-1 me-2">Callsign</label>
                                <select class="form-control callsign-select" name="positions[${positionIndex}][callsign]" required>
                                    <option value="" disabled selected>Select a position</option>
                                    ${positionsData.map(pos => `<option value="${pos["callsign"]}">${pos["callsign"]}</option>`).join("")}
                                </select>
                                <input type="text" class="form-control callsign-input d-none" name="positions[${positionIndex}][callsign]" disabled required>
                            </div>
                        </div>
                        <div class="col-md-2">
                            <div class="form-group">
                                <label class="form-label my-1 me-2">Section</label>
                                <select class="form-control" name="positions[${positionIndex}][section]" required>
                                    <option value="1">1</option>
                                    <option value="2">2</option>
                                    <option value="3">3</option>
                                    <option value="4">4</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-2">
                            <div class="form-group">
                                <label class="form-label d-flex align-items-center my-1 me-2">Local Booking</label>
                                <input type="hidden" name="positions[${positionIndex}][local_booking]" value="0">
                                <input type="checkbox" class="local-booking" name="positions[${positionIndex}][local_booking]" value="1">
                            </div>
                        </div>
                        <div class="col-md-2">
                            <div class="form-group">
                                <label class="form-label my-1 me-2">Start Time</label>
                                <input type="time" class="form-control" name="positions[${positionIndex}][start_time]">
                            </div>
                        </div>
                        <div class="col-md-2">
                            <div class="form-group">
                                <label class="form-label my-1 me-2">End Time</label>
                                <input type="time" class="form-control" name="positions[${positionIndex}][end_time]">
                            </div>
                        </div>
                    </div>
                    <button type="button" class="btn btn-danger btn-sm remove-position">Remove</button>
                `;

                positionsContainer.appendChild(positionDiv);

                setupPositionListeners(positionDiv);
            }

            document.querySelectorAll(".position-entry").forEach(setupPositionListeners);

            addPositionBtn.addEventListener("click", createPositionField);
        });
    </script>
@endsection
