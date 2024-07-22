@extends('layouts.public.app')
@section('title', 'Calendar - '. $calendar->name)
@section('css')
    <style>
        @media screen and (max-width:767px) { .fc-toolbar.fc-header-toolbar {font-size: 60%}}
    </style>
@endsection
@section('content')
    <div class="card shadow mb-4">
        <div class="card-body">
            <div id='calendar'></div>
        </div>
    </div>
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
                timeZone: 'UTC',
                events: function(fetchInfo, successCallback, failureCallback) {
                    fetch(`/api/calendars/{{ $calendar->id }}/events`, {
                        method: 'GET',
                        headers: {
                            'Authorization': `Bearer ${event_api_token}`,
                            'Content-Type': 'application/json'
                        }
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (Array.isArray(data.data)) {
                            const events = data.data.map(event => ({
                                id: event.id,
                                title: event.title,
                                start: event.start_date,
                                end: event.end_date,
                                description: event.description,
                                allDay: event.is_full_day,
                                url: `/events/${event.id}`
                            }));
                            successCallback(events);
                        } else {
                            console.error('No data or incorrect data format:', data);
                            failureCallback(new Error('No data or incorrect data format'));
                        }
                    })
                    .catch(error => {
                        console.error('Error fetching events:', error);
                        failureCallback(error);
                    });
                },
                editable: false,
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