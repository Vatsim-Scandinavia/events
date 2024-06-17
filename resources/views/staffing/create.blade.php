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
                                            @foreach ($allData as $event)
                                                @if (!App\Models\Staffing::find($event['id']))
                                                    <option value="{{ $event['id'] }}">{{ $event['title'] }}</option>
                                                @endif
                                            @endforeach
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
                                    <div class="form-check">
                                        <input type="hidden" name="restrict_booking" value="0">
                                        <input class="form-check-input" type="checkbox" value="1" name="restrict_booking" id="restrict_booking">
                                        <label for="restrict_booking" class="form-label my-1 me-2">Restrict Booking</label>
                                    </div>
                                </div>
                                <div class="col-xs-12 col-sm-12 col-md-12 mb-2">
                                    <div class="form-group">
                                        <label for="week_int" class="form-label my-1 me-2">Week interval (1-4) <i class="fas fa-xs fa-asterisk" style="color: red;"></i></label>
                                        <input type="number" min="1" max="4" id="week_int" class="form-control" type="text" name="week_int" value="{{ old('week_int') }}" required>
                                    </div>
                                </div>
                                <div class="col-xs-12 col-sm-12 col-md-12 mb-2">
                                    <div class="form-group">
                                        <label for="area" class="form-label my-1 me-2">FIR <i class="fas fa-xs fa-asterisk" style="color: red;"></i></label>
                                        <select name="area" id="area" class="form-control my-1 me-sm-2" required>
                                            <option disabled selected>Select FIR</option>
                                            @foreach ($areas as $area)
                                                <option value="{{ $area->id }}">{{ $area->name }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                </div>
                                <div class="col-xs-12 col-sm-12 col-md-12 mb-2">
                                    <div class="form-group">
                                        <label for="channel_id" class="form-label my-1 me-2">Discord Channels <i class="fas fa-xs fa-asterisk" style="color: red;"></i></label>
                                        <select name="channel_id" id="channel_id" class="form-control my-1 me-sm-2" required>
                                            <option disabled selected>Select Discord Channel</option>
                                            @foreach ($channels as $channel)
                                                <option value="{{ $channel['id']}}">#{{ $channel['name'] }}</option>
                                            @endforeach
                                        </select>
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
            var simplemde1 = new SimpleMDE({
                element: document.getElementById('description'),
                status: false,
                toolbar: ["bold", "italic", "heading-3", "|", "quote", "unordered-list", "ordered-list", "|", "link", "preview", "side-by-side", "fullscreen", "|", "guide"],
                insertTexts: {
                    link: ["[","](link)"],
                }
            });

            var simplemde2 = new SimpleMDE({
                element: document.getElementById('section_1_title'),
                status: false,
                toolbar: ["bold", "italic", "heading-3", "|", "quote", "unordered-list", "ordered-list", "|", "link", "preview", "side-by-side", "fullscreen", "|", "guide"],
                insertTexts: {
                    link: ["[","](link)"],
                }
            });

            var simplemde2 = new SimpleMDE({
                element: document.getElementById('section_2_title'),
                status: false,
                toolbar: ["bold", "italic", "heading-3", "|", "quote", "unordered-list", "ordered-list", "|", "link", "preview", "side-by-side", "fullscreen", "|", "guide"],
                insertTexts: {
                    link: ["[","](link)"],
                }
            });

            var simplemde2 = new SimpleMDE({
                element: document.getElementById('section_3_title'),
                status: false,
                toolbar: ["bold", "italic", "heading-3", "|", "quote", "unordered-list", "ordered-list", "|", "link", "preview", "side-by-side", "fullscreen", "|", "guide"],
                insertTexts: {
                    link: ["[","](link)"],
                }
            });

            var simplemde2 = new SimpleMDE({
                element: document.getElementById('section_4_title'),
                status: false,
                toolbar: ["bold", "italic", "heading-3", "|", "quote", "unordered-list", "ordered-list", "|", "link", "preview", "side-by-side", "fullscreen", "|", "guide"],
                insertTexts: {
                    link: ["[","](link)"],
                }
            });
        });
    </script>
@endsection
