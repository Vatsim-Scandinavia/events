@extends('layouts.app')

@section('title', 'Staffings')

@section('title-flex')
    @can('create', \App\Models\Staffing::class)
        <a href="{{ route('staffings.create') }}" class="btn btn-sm btn-success btn-icon-split">
            <span class="icon text-white-50"><i class="fas fa-plus"></i></span>
            <span class="text">Create Staffing</span>
        </a>
    @endcan
@endsection

@section('content')
<div class="row">
    <div class="col-12">
        <div class="card shadow mb-4">
            <div class="card-header bg-primary py-3">
                <h6 class="m-0 fw-bold text-white text-center">Staffings Overview</h6> 
            </div>

            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-striped table-sm table-hover mb-0" 
                           data-toggle="table" data-pagination="true" data-filter-control="true">
                        <thead class="table-light">
                            <tr>
                                <th data-field="id" data-sortable="true">ID</th>
                                <th data-field="title" data-sortable="true" data-filter-control="input">Event</th>
                                <th data-field="date" data-sortable="true">Next Occurrence</th>
                                <th class="text-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($staffings as $staffing)
                                @can('view', $staffing)
                                    <tr>
                                        <td class="align-middle">{{ $staffing->id }}</td>
                                        <td class="align-middle">
                                            <strong>{{ $staffing->instance->event->title ?? 'Unknown Event' }}</strong>
                                        </td>
                                        <td class="align-middle">
                                            @if($staffing->instance)
                                                {{ $staffing->instance->start_time->format('d/m/Y, H:i') }}z - 
                                                {{ $staffing->instance->end_time->format($staffing->instance->end_time->isSameDay($staffing->instance->start_time) ? 'H:i' : 'd/m/Y, H:i') }}z
                                            @else
                                                <span class="text-danger">No Instance Found</span>
                                            @endif
                                        </td>
                                        <td class="text-right align-middle">
                                            {{-- 1. Actions Dropdown --}}
                                            <div class="btn-group">
                                                <a class="btn btn-sm btn-info dropdown-toggle" href="#" role="button" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                                                    <i class="fas fa-gears"></i> Actions
                                                </a>
                                                <div class="dropdown-menu">
                                                    <a class="dropdown-item" href="{{ route('staffings.refresh', $staffing) }}" data-toggle="tooltip" title="Refreshes the data of the event on discord">Refresh Event data</a>
                                                    
                                                    <a class="dropdown-item" href="{{ route('staffings.manreset', $staffing) }}" 
                                                    data-toggle="tooltip" title="Manually resets the whole staffing. Use with caution."
                                                    onclick="return confirm('This will clear all current bookings. Are you sure?')">
                                                    Manual reset (Use with caution)
                                                    </a>
                                                </div>
                                            </div>

                                            {{-- 2. Edit Button --}}
                                            @can('update', $staffing)
                                            <div class="btn-group">
                                                <a class="btn btn-sm btn-primary" href="{{ route('staffings.edit', $staffing->id) }}">
                                                    <i class="fas fa-edit"></i> Edit
                                                </a>
                                            </div>
                                            @endcan

                                            {{-- 3. Delete Button --}}
                                            @can('destroy', $staffing)
                                            <div class="btn-group">
                                                <form method="POST" action="{{ route('staffings.destroy', $staffing->id) }}" style="display:inline" onsubmit="return confirm('Are you sure you want to delete this event? - {{ $staffing->instance->event->title ?? '' }}')">
                                                    @method('DELETE')
                                                    @csrf
                                                    <button class="btn btn-sm btn-danger" type="submit">
                                                        <i class="fas fa-trash"></i> Delete Staffing
                                                    </button>
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