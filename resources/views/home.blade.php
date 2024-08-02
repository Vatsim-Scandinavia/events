@extends('layouts.app')
@section('title', 'Home')
@section('content')
    <div class="card shadow-sm">
        <div class="card-header bg-primary text-white">
            <h5 class="m-0">Upcoming Events</h5>
        </div>
        <div class="card-body">
            <ul class="list-group list-group-flush">
                @if ($upcomingEvents->isNotEmpty())
                    @foreach($upcomingEvents as $event)
                        <li class="list-group-item d-flex justify-content-between align-items-center">
                            <img src={{ asset('storage/banners/' . $event->image) }} height="100">
                            <span>{{ $event->title }}</span>
                            <div>
                                <a href="{{ route('events.show', $event->id) }}" class="btn btn-info btn-sm">View Event</a>
                                <a href="{{ route('calendar', $event->calendar) }}" class="btn btn-secondary btn-sm">View Calendar</a>
                            </div>
                        </li>
                    @endforeach
                @else
                    <span>No Events Available</span>
                @endif
            </ul>
        </div>
    </div>

    @if ($calendar)
        <div class="card mt-5 shadow-sm">
            <div class="card-header bg-primary text-white">
                <h5 class="m-0">{{ $calendar->name }}</h5>
            </div>
            <div class="card-body">
                <div id="calendar"></div>
            </div>
        </div>
    @endif

    <footer class="text-center mt-5 mb-3">
        <a href="https://github.com/Vatsim-Scandinavia/events" target="_blank">Event Manager v{{ config('app.version') }}</a>
    </footer>
@endsection
@section('js')
    @vite(['resources/js/fullcalendar.js'])
    @if ($calendar)
        <script>
            document.addEventListener('DOMContentLoaded', function() {
                var calendarEl = document.getElementById('calendar');

                var calendar = new Calendar(calendarEl, {
                    plugins: [
                        dayGridPlugin,
                        interactionPlugin,
                        bootstrapPlugin,
                        timeGridPlugin,
                        listPlugin
                    ],
                    themeSystem: 'bootstrap',
                    firstDay: 1,
                    eventColor: '#1a475f',
                    nowIndicator: true,
                    longPressDelay: 0,
                    eventTimeFormat: {
                        hour: "2-digit",
                        minute: "2-digit",
                        hour12: false
                    },
                    slotLabelFormat: {
                        hour: '2-digit',
                        minute: '2-digit',
                        hour12: false
                    },
                    dayMaxEvents: 4,
                    timeZone: 'UTC',
                    initialView: 'dayGridMonth',
                    events: @json($events),
                });

                function updateToolbar() {
                    if (window.innerWidth < 768) {
                        calendar.setOption('headerToolbar', {
                            left: 'prev,next today',
                            center: 'title',
                            right: 'dayGridMonth,timeGridDay'
                        });
                    } else {
                        calendar.setOption('headerToolbar', {
                            left: 'dayGridMonth,dayGridWeek,timeGridDay,listMonth',
                            center: 'title',
                            right: 'today prevYear,prev,next,nextYear'
                        });
                    }
                }

                updateToolbar(); // Set initial toolbar based on screen size
                window.addEventListener('resize', updateToolbar, { passive: true }); // Update toolbar on resize with passive option

                calendar.render();
            });
        </script>
    @endif
@endsection