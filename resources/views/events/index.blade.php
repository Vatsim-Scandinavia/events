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
                                                    <span class="badge badge-light text-dark">
                                                        <i class="far fa-calendar-alt"></i> {{ $event->nextInstance->start_time->format('d-m-Y H:i') }}z
                                                    </span>
                                                @else
                                                    <span class="text-muted small italic">No upcoming dates</span>
                                                @endif
                                            </td>
                                            <td>
                                                @if($event->nextInstance)
                                                    {{ $event->nextInstance->end_time->format('d-m-Y H:i') }}z
                                                @else
                                                    <span class="text-muted">-</span>
                                                @endif
                                            </td>
                                            <td class="text-center">
                                                @if($event->recurrence_unit)
                                                    <i class="fas fa-sync-alt text-success" title="Repeats {{ $event->recurrence_unit }}"></i>
                                                @else
                                                    <i class="fas fa-minus text-gray-400"></i>
                                                @endif
                                            </td>
                                            <td>{{ $event->calendar->name }}</td>
                                            <td>{{ $event->user->name }}</td>
                                            <td>
                                                <div class="d-flex flex-column" style="gap: 0.25rem;">
                                                    @if($event->nextInstance)
                                                        {{-- Pointing to the specific instance is key for the staffing sheet --}}
                                                        <a class="btn btn-sm btn-info" href="{{ route('events.show', $event->id) }}?instance={{ $event->nextInstance->id }}">
                                                            <i class="fas fa-eye fa-fw"></i> Show
                                                        </a>
                                                    @else
                                                        <button class="btn btn-sm btn-secondary disabled" disabled>
                                                            <i class="fas fa-ban fa-fw"></i> Ended
                                                        </button>
                                                    @endif

                                                    @can('update', $event)
                                                        <a class="btn btn-sm btn-primary" href="{{ route('events.edit', $event->id) }}">
                                                            <i class="fas fa-edit fa-fw"></i> Edit
                                                        </a>
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