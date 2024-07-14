@extends('layouts.app')
@section('title', 'Calendar - '. $calendar->name)
@section('content')
<div class="card shadow mb-4">
    <div class="card-body">
        <div id='calendar'></div>
    </div>
</div>
@endsection
@section('js')
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
                headerToolbar: {
                    left: 'dayGridMonth,dayGridWeek,timeGridDay,listMonth',
                    center: 'title',
                    right: 'today prevYear,prev,next,nextYear',
                },
                initialView: 'dayGridMonth',
                themeSystem: 'bootstrap',
                eventColor: '#1a475f',
                nowIndicator: true,
                timeZone: 'UTC',
                events: function(fetchInfo, successCallback, failureCallback) {
                    fetch(`/api/calendars/{{ $calendar->id }}/events`, {
                        method: 'GET',
                        headers: {
                            'Authorization': `Bearer ${event_api_token}`,
                            'Content-Type': 'application/json'
                        }
                    })
                    .then(response => {
                        return response.json();
                    })
                    .then(data => {
                        if (Array.isArray(data.data)) {
                            const events = data.data.map(event => ({
                                id: event.id,
                                title: event.title,
                                start: event.start_date,
                                end: event.end_date,
                                description: event.description,
                                isFullDay: event.is_full_day,
                                isRecurring: event.is_recurring,
                                recurrencePattern: event.recurrence_pattern,
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
                selectable: true,
                editable: false,
            });

            calendar.render();
        });
    </script>
@endsection