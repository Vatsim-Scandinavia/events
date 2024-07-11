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
                <div class="card-header bg-primary py-3 d-flex flex-row align-items-center justify-content-center">
                    <h6 class="m-0 fw-bold text-white">Event Overview</h6> 
                </div>

                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-striped table-sm table-hover table-leftpadded mb-0" width="100%" cellspacing="0" data-page-size="15" data-toggle="table" data-pagination="true" data-filter-control="true" data-sort-reset="true">
                            <thead class="table-light">
                                <tr>
                                    <th data-field="id" data-sortable="true" data-filter-control="input">ID</th>
                                    <th data-field="title" data-sortable="true" data-filter-control="input">Title</th>
                                    <th data-field="start_date" data-sortable="true" data-filter-control="input">Start Date</th>
                                    <th data-field="end_date" data-sortable="true" data-filter-control="input">End Date</th>
                                    <th data-field="actions" data-sortable="true" data-filter-control="input">Actions</th>
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
                                            <td>
                                                
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