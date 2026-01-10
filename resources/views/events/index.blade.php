@extends('layouts.app')
@section('title', 'Events')
@section('title-flex')
    @can('create', \App\Models\Event::class)
        <a href="{{ route('events.create') }}" class="btn btn-sm btn-success btn-icon-split">
            <span class="icon text-white-50">
                <i class="fas fa-plus"></i>
            </span>
            <span class="text">Create Event</span>
        </a>
    @endcan
@endsection
@section('content')
    <div class="row">
        <div class="col-xl-12 col-md-12 mb-12">
            <div class="card shadow mb-4">
                <div class="card-header bg-primary py-3 d-flex flex-row align-items-center">
                    <h6 class="m-0 fw-bold text-white">Event Overview</h6> 
                </div>

                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-striped table-sm table-hover table-leftpadded mb-0" width="100%" cellspacing="0" 
                            data-page-size="10" data-toggle="table" data-pagination="true" data-filter-control="true" data-sort-reset="true" data-sort-select-options="true">
                            <thead class="table-light">
                                <tr>
                                    <th data-field="title" data-sortable="true" data-filter-control="input">Title</th>
                                    <th data-field="start_date" data-sortable="true" data-filter-control="input">Start Date</th>
                                    <th data-field="end_date" data-sortable="true" data-filter-control="input">End Date</th>
                                    <th data-field="recurring" data-sortable="true" data-filter-control="select">Recurring</th>
                                    <th data-field="calendar" data-sortable="true" data-filter-control="select">Calendar</th>
                                    <th data-field="createdby" data-sortable="true" data-filter-control="select">Created by</th>
                                    <th data-field="actions" data-sortable="false" data-filter-control="false">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($events as $event)
                                    @can('view', $event)
                                        <tr>
                                            <td>{{ $event->title }}</td>
                                            <td>
                                                @if($event->nextInstance)
                                                    {{ $event->nextInstance->start_time->format('d-m-Y H:i') }}z
                                                @else
                                                    <span class="text-muted">TBD</span>
                                                @endif
                                            </td>
                                            <td>
                                                @if($event->nextInstance)
                                                    {{ $event->nextInstance->end_time->format('d-m-Y H:i') }}z
                                                @else
                                                    <span class="text-muted">TBD</span>
                                                @endif
                                            </td>
                                            <td>{{ $event->recurrence_interval ? 'Yes' : 'No' }}</td>
                                            <td>{{ $event->calendar->name }}</td>
                                            <td>{{ $event->user->name }}</td>
                                            <td>
                                                <div class="d-flex flex-column" style="gap: 0.25rem;">
                                                    @if($event->nextInstance)
                                                        <a class="btn btn-sm btn-info" href="{{ route('events.show', ['event' => $event->id, 'instance' => $event->nextInstance->id]) }}">
                                                            <i class="fas fa-eye fa-fw"></i> Show
                                                        </a>
                                                    @else
                                                        <a class="btn btn-sm btn-info disabled" href="#">
                                                            <i class="fas fa-eye fa-fw"></i> No occurrences
                                                        </a>
                                                    @endif

                                                    @can('update', $event)
                                                        <a class="btn btn-sm btn-primary" href="{{ route('events.edit', $event->id) }}">
                                                            <i class="fas fa-edit fa-fw"></i> Edit
                                                        </a>
                                                    @endcan

                                                    @can('destroy', $event)
                                                        <form method="POST" action="{{ route('events.destroy', $event->id) }}" class="d-grid"
                                                            onsubmit="return confirm('Are you sure you want to delete this event? - {{ $event->title }}')">
                                                            @method('DELETE')
                                                            @csrf
                                                            <button class="btn btn-sm btn-danger" type="submit">
                                                                <i class="fas fa-trash fa-fw" aria-hidden="true"></i> Delete
                                                            </button>
                                                        </form>
                                                    @endcan
                                                </div>
                                            </td>
                                        </tr>
                                    @endcan
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
@section('js')
    @vite(['resources/js/bootstrap-table.js'])
@endsection