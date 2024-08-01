@extends('layouts.app')
@section('title', 'Create Calendar')
@section('content')
    <div class="row">
        <div class="col-xl-6 col-md-12 mb-12">
            <div class="card shadow mb-4">
                <div class="card-header bg-primary py-3 d-flex flex-row align-items-center">
                    <h6 class="m-0 fw-bold text-white">User input</h6> 
                </div>
                <div class="card-body">
                    <form action="{{ route('calendars.store') }}" method="post">
                        @csrf
                        <div class="container-fluid">
                            <div class="row pt-2">
                                <div class="col-xl-12 col-md-12 mb-12">
                                    <div class="form-group mb-4">
                                        <label for="event" class="form-label my-1 me-2">Calendar Name<i class="fas fa-xs fa-asterisk" style="color: red;"></i></label>
                                        <input type="text" name="name" id="name" class="form-control @error('title') is-invalid @enderror" value="{{ old('name') }}" required>
                                        @error('name')
                                            <span class="text-danger">{{ $errors->first('name') }}</span>
                                        @enderror
                                    </div>

                                    <div class="form-group mb-4">
                                        <label for="description" class="form-label my-1 me-2">Description</label>
                                        <textarea class="form-control @error('description') is-invalid @enderror" name="description" id="description" rows="8">{{ old('description') }}</textarea>
                                        @error('description')
                                            <span class="text-danger">{{ $errors->first('description') }}</span>
                                        @enderror
                                    </div>

                                    <div class="form-check">
                                        <input class="form-check-input" type="radio" name="public" id="public" value="1" {{ old('public') == "1" ? 'checked' : '' }} required>
                                        <label class="form-check-label" for="public">
                                            Public Calendar
                                        </label>
                                    </div>

                                    <div class="form-check mb-4">
                                        <input class="form-check-input" type="radio" name="public" id="private" value="0" {{ old('public') == "0" ? 'checked' : '' }} required>
                                        <label class="form-check-label" for="private">
                                            Private Calendar
                                        </label>
                                    </div>
                                </div>
                                <button type="submit" id="submit-btn" class="btn btn-success">Create Calendar</button>
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
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            var simplemde1 = new SimpleMDE({
                element: document.getElementById('description'),
                status: false,
                toolbar: ["bold", "italic", "heading-3", "|", "quote", "unordered-list", "ordered-list", "|", "link", "preview", "side-by-side", "fullscreen", "|", "guide"],
                insertTexts: {
                    link: ["[","](link)"],
                }
            });
        });
    </script>
@endsection