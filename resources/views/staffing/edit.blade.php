@extends('layouts.app')
@section('title', 'Edit Staffing - ' . $staffing->instance->event->title)
@section('content')
    <div class="row">
        <div class="col-xl-12 col-md-12 mb-12">
            <div class="card shadow mb-4">
                <div class="card-header bg-primary py-3 d-flex flex-row align-items-center justify-content-center">
                    <h6 class="m-0 fw-bold text-white">User input</h6> 
                </div>
                <div class="card-body">
                    <form action="{{ route('staffings.update', $staffing) }}" method="post">
                        @method('patch')
                        @csrf
                        <div class="container-fluid">
                            <div class="row pt-2">
                                <div class="col-xs-12 col-sm-12 col-md-12 mb-2">
                                    <div class="form-group">
                                        <label for="event" class="form-label my-1 me-2">Event <i class="fas fa-xs fa-asterisk" style="color: red;"></i></label>
                                        <select name="event" id="event" class="form-control my-1 me-sm-2" required>
                                            <option disabled>Select Event</option>
                                            <option value="{{ $staffing->instance->event->id }}" selected disabled>
                                                {{ $staffing->instance->event->title }}
                                            </option>
                                        </select>
                                    </div>
                                </div>
                                <div class="col-xs-12 col-sm-12 col-md-12 mb-2">
                                    <div class="form-group">
                                        <label for="description" class="form-label my-1 me-2">Description <i class="fas fa-xs fa-asterisk" style="color: red;"></i></label>
                                        <textarea class="form-control @error('description') is-invalid @enderror" name="description" id="description" rows="8">{{ $staffing->description }}</textarea>
                                        @error('description')
                                            <span class="text-danger">{{ $errors->first('description') }}</span>
                                        @enderror
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="section_1_title" class="form-label my-1 me-2">Section 1 Title <i class="fas fa-xs fa-asterisk" style="color: red;"></i></label>
                                        <textarea class="form-control @error('section_1_title') is-invalid @enderror" name="section_1_title" id="section_1_title">{{ $staffing->section_1_title }}</textarea>
                                        @error('section_1_title')
                                            <span class="text-danger">{{ $errors->first('section_1_title') }}</span>
                                        @enderror
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="section_2_title" class="form-label my-1 me-2">Section 2 Title</label>
                                        <textarea class="form-control @error('section_2_title') is-invalid @enderror" name="section_2_title" id="section_2_title">{{ $staffing->section_2_title }}</textarea>
                                        @error('section_2_title')
                                            <span class="text-danger">{{ $errors->first('section_2_title') }}</span>
                                        @enderror
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="section_3_title" class="form-label my-1 me-2">Section 3 Title</label>
                                        <textarea class="form-control @error('section_3_title') is-invalid @enderror" name="section_3_title" id="section_3_title">{{ $staffing->section_3_title }}</textarea>
                                        @error('section_3_title')
                                            <span class="text-danger">{{ $errors->first('section_3_title') }}</span>
                                        @enderror
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="section_4_title" class="form-label my-1 me-2">Section 4 Title</label>
                                        <textarea class="form-control @error('section_4_title') is-invalid @enderror" name="section_4_title" id="section_4_title">{{ $staffing->section_4_title }}</textarea>
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
                                        <select name="channel_id" id="channel_id" class="form-control my-1 me-sm-2" disabled>
                                            <option disabled selected>Select Discord Channel</option>
                                            @foreach ($channels as $channel)
                                                @if(isset($channels[0]) && !is_array($channels[0]) && str_starts_with($channels[0], 'Error:'))
                                                    <option disabled>{{ $channels[0] }}</option>
                                                    @break
                                                @else
                                                    <option value="{{ $channel['id'] }}" {{ $channel['id'] == $staffing->channel_id ? 'selected' : '' }}>#{{ $channel['name'] }}</option>
                                                @endif
                                            @endforeach
                                        </select>
                                    </div>
                                </div>
                                <div class="col-xs-12 col-sm-12 col-md-12 mb-2">
                                    <div class="form-group">
                                        <label for="positions" class="form-label my-1 me-2">Positions <i class="fas fa-xs fa-asterisk" style="color: red;"></i></label>
                                        @error('positions')
                                            <div class="alert alert-danger py-2">
                                                <i class="fas fa-exclamation-circle me-2"></i> {{ $message }}
                                            </div>
                                        @enderror
                                        <div id="positions-container">
                                            @foreach ($staffing->positions as $index => $position)
                                                <div class="position-entry mb-3 p-3 border rounded shadow-sm">
                                                    <input type="hidden" name="positions[{{ $index }}][id]" value="{{ $position->id }}">
                                                    <div class="row align-items-end">
                                                        <div class="col-md-3">
                                                            <label class="form-label small fw-bold">Callsign</label>
                                                            <select class="form-control callsign-select {{ $position->local_booking ? 'd-none' : '' }}" name="positions[{{ $index }}][callsign]" {{ $position->local_booking ? 'disabled' : '' }} required>
                                                                @foreach ($positions as $pos)
                                                                    <option value="{{ $pos['callsign'] }}" {{ $pos['callsign'] == $position->callsign ? 'selected' : '' }}>{{ $pos['callsign'] }}</option>
                                                                @endforeach
                                                            </select>
                                                            <input type="text" class="form-control callsign-input {{ !$position->local_booking ? 'd-none' : '' }}" name="positions[{{ $index }}][callsign]" value="{{ $position->callsign }}" {{ !$position->local_booking ? 'disabled' : '' }} required>
                                                        </div>

                                                        <div class="col-md-2">
                                                            <label class="form-label small fw-bold">Section</label>
                                                            <select class="form-control" name="positions[{{ $index }}][section]" required>
                                                                @for ($i = 1; $i <= 4; $i++)
                                                                    <option value="{{ $i }}" {{ $i == $position->section ? 'selected' : '' }}>{{ $i }}</option>
                                                                @endfor
                                                            </select>
                                                        </div>

                                                        <div class="col-md-2">
                                                            <label class="form-label small fw-bold">Start</label>
                                                            <input type="time" class="form-control" name="positions[{{ $index }}][start_time]" value="{{ $position->start_time }}">
                                                        </div>

                                                        <div class="col-md-2">
                                                            <label class="form-label small fw-bold">End</label>
                                                            <input type="time" class="form-control" name="positions[{{ $index }}][end_time]" value="{{ $position->end_time }}">
                                                        </div>

                                                        <div class="col-md-2 text-center">
                                                            <label class="form-label small fw-bold d-block">Local</label>
                                                            <input type="hidden" name="positions[{{ $index }}][local_booking]" value="0">
                                                            <input type="checkbox" class="local-booking" name="positions[{{ $index }}][local_booking]" value="1" {{ $position->local_booking ? 'checked' : '' }}>
                                                        </div>

                                                        <div class="col-md-1">
                                                            <button type="button" class="btn btn-outline-danger btn-sm remove-position w-100">
                                                                <i class="fas fa-trash"></i>
                                                            </button>
                                                        </div>
                                                    </div>
                                                </div>
                                            @endforeach
                                        </div>
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

            let newPositionCounter = 0;

            function createPositionField() {
                let positionIndex = 'new_' + Date.now() + '_' + (newPositionCounter++);
                let positionDiv = document.createElement("div");
                positionDiv.classList.add("position-entry", "mb-3", "p-3", "border", "rounded", "shadow-sm");

                positionDiv.innerHTML = `
                    <div class="row align-items-end">
                        <div class="col-md-3">
                            <label class="form-label small fw-bold">Callsign</label>
                            <select class="form-control callsign-select" name="positions[${positionIndex}][callsign]" required>
                                <option value="" disabled selected>Select a position</option>
                                ${positionsData.map(pos => `<option value="${pos["callsign"]}">${pos["callsign"]}</option>`).join("")}
                            </select>
                            <input type="text" class="form-control callsign-input d-none" name="positions[${positionIndex}][callsign]" disabled required>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label small fw-bold">Section</label>
                            <select class="form-control" name="positions[${positionIndex}][section]" required>
                                <option value="1">1</option><option value="2">2</option><option value="3">3</option><option value="4">4</option>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label small fw-bold">Start</label>
                            <input type="time" class="form-control" name="positions[${positionIndex}][start_time]">
                        </div>
                        <div class="col-md-2">
                            <label class="form-label small fw-bold">End</label>
                            <input type="time" class="form-control" name="positions[${positionIndex}][end_time]">
                        </div>
                        <div class="col-md-2 text-center">
                            <label class="form-label small fw-bold d-block">Local</label>
                            <input type="hidden" name="positions[${positionIndex}][local_booking]" value="0">
                            <input type="checkbox" class="local-booking" name="positions[${positionIndex}][local_booking]" value="1">
                        </div>
                        <div class="col-md-1">
                            <button type="button" class="btn btn-outline-danger btn-sm remove-position w-100">
                                <i class="fas fa-trash"></i>
                            </button>
                        </div>
                    </div>
                `;

                positionsContainer.appendChild(positionDiv);
                setupPositionListeners(positionDiv);
            }

            document.querySelectorAll(".position-entry").forEach(setupPositionListeners);

            addPositionBtn.addEventListener("click", createPositionField);

            $('form').on('submit', function(e) {
                let sections = {};
                let hasDuplicate = false;
                let duplicateInfo = "";

                // Loop through every position entry currently in the form
                $('.position-entry').each(function() {
                    // Find the callsign (check both select and manual input)
                    let callsignSelect = $(this).find('.callsign-select');
                    let callsignInput = $(this).find('.callsign-input');
                    
                    // Determine which one is currently active/visible
                    let callsign = callsignSelect.is(':visible') ? callsignSelect.val() : callsignInput.val();
                    let section = $(this).find('select[name*="[section]"]').val();

                    if (callsign && section) {
                        callsign = callsign.trim().toUpperCase(); // Standardize for comparison

                        if (!sections[section]) {
                            sections[section] = [];
                        }
                        
                        if (sections[section].includes(callsign)) {
                            hasDuplicate = true;
                            duplicateInfo = `Callsign: ${callsign} in Section: ${section}`;
                            return false; // Break the .each loop
                        }
                        
                        sections[section].push(callsign);
                    }
                });

                if (hasDuplicate) {
                    e.preventDefault(); // Stop form from sending to the server
                    alert(`Duplicate Entry Detected!\n\n${duplicateInfo}\n\nPlease ensure each callsign is unique within its section.`);
                }
            });
        });
    </script>
@endsection