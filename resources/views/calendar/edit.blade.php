@extends('layouts.auth.app')
@section('title', 'Create Calendar')
@section('content')
    <div class="row justify-content-center">
        <div class="col-xl-6 col-md-12 mb-12">
            <div class="card shadow mb-4">
                <div class="card-header bg-primary py-3 d-flex flex-row align-items-center justify-content-center">
                    <h6 class="m-0 fw-bold text-white">User input</h6> 
                </div>
                <div class="card-body">
                    <form action="{{ route('calendars.update', $calendar->id) }}" method="post">
                        @csrf
                        @method('PATCH')
                        <div class="container-fluid">
                            <div class="row pt-2">
                                <div class="col-xl-12 col-md-12 mb-12">
                                    <div class="form-group mb-4">
                                        <label for="event" class="form-label my-1 me-2">Calendar Name<i class="fas fa-xs fa-asterisk" style="color: red;"></i></label>
                                        <input type="text" name="name" id="name" class="form-control @error('title') is-invalid @enderror" value="{{ $calendar->name }}" required>
                                        @error('name')
                                            <span class="text-danger">{{ $errors->first('name') }}</span>
                                        @enderror
                                    </div>

                                    <div class="form-group mb-4">
                                        <label for="description" class="form-label my-1 me-2">Description</label>
                                        <textarea class="form-control @error('description') is-invalid @enderror" name="description" id="description" rows="8">{{ $calendar->description }}</textarea>
                                        @error('description')
                                            <span class="text-danger">{{ $errors->first('description') }}</span>
                                        @enderror
                                    </div>

                                    <div class="form-check mb-4">
                                        <input type="hidden" name="public" value="0">
                                        <input class="form-check-input" type="checkbox" value="1" name="public" id="public" {{$calendar->public == "1" ? 'checked' : '' }}>
                                        <label for="public" class="form-label my-1 me-2">Public calendar?</label>
                                    </div>
                                </div>

                                <button type="submit" id="submit-btn" class="btn btn-success">Update Calendar</button>
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