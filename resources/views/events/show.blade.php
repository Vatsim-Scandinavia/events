@extends('layouts.public.app')
@section('title', $event->title)
@section('content')
    <div class="container mt-5">
        <div class="card shadow mb-4">
            <div class="card-body text-center">
                <h1 class="card-title">{{ $event->title }}</h1>
                <p class="card-text text-muted">Hosted by {{ $event->area->name }} FIR in <a href="{{ route('calendar', $event->calendar->id) }}" class="card-link">{{ $event->calendar->name }}</a></p>
            </div>
        </div>

        @if($event->image)
        <div class="card shadow mb-4">
            <div class="card-body text-center">
                <img src="{{ asset('storage/images/' . $event->image) }}" alt="{{ $event->title }}" class="img-fluid">
            </div>
        </div>
        @endif

        <div class="card shadow mb-4">
            <div class="card-body">
                <h2 class="card-title">Description</h2>
                <p class="card-text">@markdown($event->description)</p>
            </div>
        </div>

        <div class="card shadow mb-4">
            <div class="card-body">
                <h2 class="card-title">Details</h2>
                <ul class="list-group list-group-flush">
                    <li class="list-group-item"><strong>Start Date:</strong> {{ \Carbon\Carbon::parse($event->start_date)->format('F j, Y, g:i a') }}</li>
                    <li class="list-group-item"><strong>End Date:</strong> {{ \Carbon\Carbon::parse($event->end_date)->format('F j, Y, g:i a') }}</li>
                    <li class="list-group-item"><strong>Full Day Event:</strong> {{ $event->is_full_day ? 'Yes' : 'No' }}</li>
                    @if($event->recurrence_interval)
                        <li class="list-group-item"><strong>Recurrence:</strong> Every {{ $event->recurrence_interval }} {{ Str::plural($event->recurrence_unit, $event->recurrence_interval) }}</li>
                        <li class="list-group-item"><strong>Recurrence End Date:</strong> {{ \Carbon\Carbon::parse($event->recurrence_end_date)->format('F j, Y, g:i a') }}</li>
                    @endif
                    <li class="list-group-item"><strong>FIR:</strong> {{ $event->area->name }}</li>
                </ul>
            </div>
        </div>
    </div>
@endsection