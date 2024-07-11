@extends('layouts.app')
@section('title', 'Calendar - '. $calendar->name)
@section('content')
    <div id='calendar'></div>
@endsection
@section('js')
    <link href='https://cdn.jsdelivr.net/npm/@fullcalendar/core@4.4.0/main.min.css' rel='stylesheet' />
    <link href='https://cdn.jsdelivr.net/npm/@fullcalendar/daygrid@4.4.0/main.min.css' rel='stylesheet' />
    <script src='https://cdn.jsdelivr.net/npm/@fullcalendar/core@4.4.0/main.min.js'></script>
    <script src='https://cdn.jsdelivr.net/npm/@fullcalendar/daygrid@4.4.0/main.min.js'></script>
    <script src='https://cdn.jsdelivr.net/npm/@fullcalendar/interaction@4.4.0/main.min.js'></script>

    <script>
        document.addEventListener('DOMContentLoaded', function(){
            var calendarEl = document.getElementById('calendar');

            var calendar = new FullCalendar.Calendar(calendarEl, {
                plugins: [ 'dayGrid', 'interaction' ],
                events: function(fetchInfo, successCallback, failureCallback) {
                    fetch('/api/calendars/1/events', {
                        method: 'GET',
                        headers: {
                            'Authorization': `Bearer 51a64a9a-a243-4317-b1ac-080952b2ca05`,
                            'Content-Type': 'application/json'
                        }
                    })
                    .then(response => response.json())
                    .then(data => {
                        const events = data.data.map(event => ({
                            id: event.id,
                            title: event.title,
                            start: `${event.start_date}`,
                            end: `${event.end_date}`,
                            description: event.description,
                            isFullDay: event.is_full_day,
                            isRecurring: event.is_recurring,
                            recurrencePattern: event.recurrence_pattern
                        }));
                        successCallback(events);
                    })
                    .catch(error => {
                        console.error('Error fetching events:', error);
                        failureCallback(error);
                    });
                },
                selectable: true,
                selectHelper: true,
                editable: false,
            });

            calendar.render();
        });
    </script>
@endsection