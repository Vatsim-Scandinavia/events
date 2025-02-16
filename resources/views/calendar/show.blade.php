@extends('layouts.app')
@section('title', 'Calendar - '. $calendar->name)
@section('css')
    <style>
        @media screen and (max-width:767px) { .fc-toolbar.fc-header-toolbar {font-size: 60%}}
    </style>
@endsection
@section('content')
    <div class="card shadow-sm">
        <div class="card-header bg-primary text-white">
            <h5 class="m-0">{{ $calendar->name }}</h5>
        </div>
        <div class="card-body">
            <div id='calendar'></div>
        </div>
    </div>
    
    <footer class="text-center mt-5 mb-3">
        <a href="https://github.com/Vatsim-Scandinavia/events" target="_blank">Event Manager v{{ config('app.version') }}</a>
    </footer>
@endsection
@section('js')
    @vite(['resources/js/fullcalendar.js'])
    <script>
        document.addEventListener('DOMContentLoaded', function(){
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
@endsection