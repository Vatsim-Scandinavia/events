@extends('layouts.app')

@section('title', 'Staffings')
@section('title-flex')
    <a href="{{ route('staffings.create') }}" class="btn btn-sm btn-success btn-icon-split">
        <span class="icon text-white-50">
            <i class="fas fa-plus"></i>
        </span>
        <span class="text">Create Staffing</span>
    </a>
@endsection
@section('content')
    <div class="row">
        <div class="col-xl-12 col-md-12 mb-12">
            <div class="card shadow mb-4">
                <div class="card-header bg-primary py-3 d-flex flex-row align-items-center justify-content-center">
                    <h6 class="m-0 fw-bold text-white">Staffings Overview</h6> 
                </div>

                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-striped table-sm table-hover table-leftpadded mb-0" width="100%" cellspacing="0" data-page-size="10" data-page-size="10" data-toggle="table" data-pagination="true" data-filter-control="true" data-sort-reset="true" data-sort-select-options="true">
                            <thead class="table-light">
                                <tr>
                                    <th data-field="id" data-sortable="true" data-filter-control="input">ID</th>
                                    <th data-field="title" data-sortable="true" data-filter-control="input">Title</th>
                                    <th data-field="date" data-sortable="true" data-filter-control="input">Date</th>
                                    <th data-field="actions" data-sortable="false" data-filter-control="false">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($staffings as $staffing)
                                    @can('view', $staffing)
                                        <tr>
                                            <td>{{ $staffing->id }}</td>
                                            <td>{{ $staffing->event->title }}</td>
                                            <td>{{ \Carbon\Carbon::parse($staffing->event->start_date)->format('d/m/Y, H:i') }}z - {{ \Carbon\Carbon::parse($staffing->event->end_date)->format('d/m/Y') == \Carbon\Carbon::parse($staffing->event->start_date)->format('d/m/Y') ? \Carbon\Carbon::parse($staffing->event->end_date)->format('H:i') : \Carbon\Carbon::parse($staffing->event->end_date)->format('d/m/Y, H:i') }}z</td>
                                            <td>
                                                <div class="btn-group">
                                                    <a class="btn btn-sm btn-info dropdown-toggle" href="#" role="button" id="dropdownMenuLink" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                                                        <i class="fas fa-gears"></i> Actions
                                                    </a>
                                                    <div class="dropdown-menu" aria-labelledby="dropdownMenuLink">
                                                        <a class="dropdown-item" href="#" data-toggle="tooltip" data-placement="top" title="Refreshes the data of the event on discord">Refresh Event data</a>
                                                        <a class="dropdown-item" href="#" data-toggle="tooltip" data-placement="top" title="Manually resets the whole staffing, removes all staffings and selects next event date. Use with caution.">Manual reset (Use with cation)</a>
                                                    </div>
                                                </div>
                                                @can('update', $staffing)
                                                    <div class="btn-group">
                                                        <a class="btn btn-sm btn-primary" href="{{ route('staffings.edit', $staffing->id) }}"><i class="fas fa-edit"></i> Edit</a>
                                                    </div>
                                                @endcan
                                                @can('destroy', $staffing)
                                                    <div class="btn-group">
                                                        <form method="POST" action="{{ route('staffings.destroy', $staffing->id) }}" style="display:inline"
                                                            onsubmit="return confirm('Are you sure you want to delete this event? - {{ $staffing->event->title }}')">
                                                            @method('DELETE')
                                                            @csrf
                                                            <button class="btn btn-sm btn-danger" type="submit"><i class="fas fa-trash" aria-hidden="true"></i> Delete Staffing</button>
                                                        </form>
                                                    </div>
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