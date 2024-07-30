@extends('layouts.auth.app')
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
        <div class="col-xl-6 col-md-12 mb-12">
            <div class="card shadow mb-4">
                <div class="card-header bg-primary py-3 d-flex flex-row align-items-center justify-content-center">
                    <h6 class="m-0 fw-bold text-white">Event Overview</h6> 
                </div>

                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-striped table-sm table-hover table-leftpadded mb-0" width="100%" cellspacing="0" 
                            data-page-size="10" data-toggle="table" data-pagination="true" data-filter-control="true" data-sort-reset="true" data-sort-select-options="true">
                            <thead class="table-light">
                                <tr>
                                    <th data-field="id" data-sortable="true" data-filter-control="input">ID</th>
                                    <th data-field="title" data-sortable="true" data-filter-control="input">Title</th>
                                    <th data-field="start_date" data-sortable="true" data-filter-control="input">Start Date</th>
                                    <th data-field="end_date" data-sortable="true" data-filter-control="input">End Date</th>
                                    <th data-field="parent" data-sortable="true" data-filter-control="select" data-filter-data-collector="tableFilterStripHtml" data-filter-strict-search="false">Parent Event</th>
                                    <th data-field="actions" data-sortable="false" data-filter-control="false">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($events as $event)
                                    @can('view', $event)
                                        <tr>
                                            <td>{{ $event->id }}</td>
                                            <td>{{ $event->title }}</td>
                                            <td>{{ \Carbon\Carbon::parse($event->start_date)->format('d-m-Y') }}</td>
                                            <td>{{ \Carbon\Carbon::parse($event->end_date)->format('d-m-Y') }}</td>
                                            <td class="text-center text-white {{ $event->parent_id == null && $event->recurrence_interval != null ? 'bg-success' : 'bg-danger' }}">
                                                @if ($event->parent_id == null && $event->recurrence_interval != null) 
                                                    <i class="fas fa-check-circle"></i><span class="d-none">Yes</span>
                                                @else 
                                                    <i class="fas fa-times-circle"></i><span class="d-none">No</span>
                                                @endif
                                            </td>
                                            <td>
                                                <a class="btn btn-info" href="{{ route('events.show', $event->id) }}"><i class="fas fa-eye"></i> Show</a>
                                                @can('update', $event)
                                                    <a class="btn btn-primary" href="{{ route('events.edit', $event->id) }}"><i class="fas fa-edit"></i> Edit</a>
                                                @endcan
                                                @can('destroy', $event)
                                                    <form method="POST" action="{{ route('events.destroy', $event->id) }}" style="display:inline"
                                                        onsubmit="return confirm('Are you sure you want to delete this event? - {{ $event->title }}')">
                                                        @method('DELETE')
                                                        @csrf
                                                        <button class="btn btn-danger" type="submit"><i class="fas fa-trash" aria-hidden="true"></i> Delete</button>
                                                    </form>
                                                @endcan
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