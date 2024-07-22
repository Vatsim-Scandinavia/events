@extends('layouts.auth.app')

@section('title', 'Dashboard')
@section('content')
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-md-8">
                <div class="card shadow mb-4">
                    <div class="card-header bg-primary py-3">
                        <h6 class="m-0 font-weight-bold text-white">Upcoming 5 Events</h6>
                    </div>
                    <div class="card-body">                        
                        @if($events->isEmpty())
                            <p>No upcoming events in the next 24 hours.</p>
                        @else
                            <ul class="list-group">
                                @foreach($events as $event)
                                    <li class="list-group-item">
                                        <h6><a href="{{ route('events.show', $event->id) }}">{{ $event->title }}</a></h6>
                                        <p>{{ Str::limit($event->description, 50) }}</p>
                                        <small>{{ \Carbon\Carbon::parse($event->start_date)->format('F j, Y, g:i a') }}</small>
                                        <div class="mt-2">
                                            <a href="{{ route('events.show', $event->id) }}" class="btn btn-primary btn-sm float-right mr-2">View More</a>
                                            <a href="{{ route('calendar', $event->calendar_id) }}" class="btn btn-outline-primary btn-sm float-right">More like this</a>
                                        </div>
                                    </li>
                                @endforeach
                            </ul>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
