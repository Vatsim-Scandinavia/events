@extends('layouts.public.app')
@section('content')
    <div class="container mt-5">
        <div class="jumbotron text-center shadow-sm">
            <h1 class="display-4">Welcome to {{ config('app.owner_name') }} Event Manager</h1>
            <hr class="my-4">
            <p>Get started by logging in to get an overview of our upcoming events.</p>
            <a class="btn btn-primary btn-lg mx-2" href="{{ route('login') }}" role="button">Login</a>
        </div>

        <div class="card-deck mt-5">
            <div class="card shadow-sm">
                <div class="card-body text-center">
                    <h5 class="card-title">Discover Community Events</h5>
                    <p class="card-text">Explore a variety of events happening in your community. Find something that interests you and get involved.</p>
                </div>
            </div>
            <div class="card shadow-sm">
                <div class="card-body text-center">
                    <h5 class="card-title">Stay Updated</h5>
                    <p class="card-text">Keep track of upcoming events and never miss out on exciting activities. Check back regularly for the latest updates.</p>
                </div>
            </div>
        </div>

        <div class="card mt-5 shadow-sm">
            <div class="card-header bg-primary text-white">
                <h5 class="m-0">Upcoming Events</h5>
            </div>
            <div class="card-body">
                <ul class="list-group list-group-flush">
                    @foreach($events as $event)
                        <li class="list-group-item d-flex justify-content-between align-items-center">
                            <span>{{ $event->title }}</span>
                            <div>
                                <a href="{{ route('events.show', $event->id) }}" class="btn btn-info btn-sm">View Event</a>
                                <a href="{{ route('calendar', $event->calendar) }}" class="btn btn-secondary btn-sm">View Calendar</a>
                            </div>
                        </li>
                    @endforeach
                </ul>
            </div>
        </div>
    </div>

    <footer class="text-center mt-5 mb-3">
        <p>&copy; 2024 Event Manager. All rights reserved.</p>
    </footer>
@endsection