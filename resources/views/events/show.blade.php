@extends('layouts.app')
@section('title', $event->title)
@section('content')

    <div class="container-event">

        @if($event->image)
            <img src="{{ asset('storage/banners/' . $event->image) }}" alt="{{ $event->title }}" class="img-fluid">
        @endif

        <div class="mt-4">
            <i class="fas fa-clock"></i>
            <strong>Start:</strong>
            {{ \Carbon\Carbon::parse($event->start_date)->format('F j, Y, H:i') }}z
        </div>

        <div class="mb-4">
            <i class="fas fa-clock"></i>
            <strong>End:</strong>
            {{ \Carbon\Carbon::parse($event->end_date)->format('F j, Y, H:i') }}z
        </div>

        @if($event->recurrence_interval)
            <div>
                <i class="fas fa-calendar-alt"></i>
                <strong>Recurrence:</strong> Every {{ $event->recurrence_interval }} {{ Str::plural($event->recurrence_unit, $event->recurrence_interval) }}
            </div>
            <div>
                <i class="fas fa-calendar-alt"></i>
                <strong>Recurrence End Date:</strong> {{ \Carbon\Carbon::parse($event->recurrence_end_date)->format('F j, Y') }}z
            </div>
        @endif

        <p class="card-text">@markdown($event->long_description)</p>
    </div>
@endsection