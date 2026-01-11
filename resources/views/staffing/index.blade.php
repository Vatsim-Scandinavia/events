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
                                            <strong>{{ $staffing->instance->event->title ?? 'Unkown Event' }}</strong>
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
                                            <div class="btn-group" role="group">
                                                {{-- Primary Action: Edit --}}
                                                @can('update', $staffing)
                                                    <a class="btn btn-sm btn-outline-primary" href="{{ route('staffings.edit', $staffing->id) }}">
                                                        <i class="fas fa-edit"></i> Edit
                                                    </a>
                                                @endcan

                                                {{-- Utility Dropdown --}}
                                                <div class="btn-group" role="group">
                                                    <button class="btn btn-sm btn-outline-secondary dropdown-toggle" type="button" data-toggle="dropdown">
                                                        <i class="fas fa-cog"></i>
                                                    </button>
                                                    <div class="dropdown-menu dropdown-menu-right">
                                                        <a class="dropdown-item" href="{{ route('staffings.refresh', $staffing) }}">
                                                            <i class="fas fa-sync-alt fa-sm fa-fw mr-2 text-gray-400"></i> Refresh Data
                                                        </a>
                                                        <a class="dropdown-item text-warning" 
                                                        href="{{ route('staffings.manreset', $staffing) }}" 
                                                        onclick="return confirm('This will clear all current bookings for this event. Are you sure?')">
                                                        <i class="fas fa-exclamation-triangle"></i> Manual Reset
                                                        </a>
                                                        
                                                        @can('destroy', $staffing)
                                                            <div class="dropdown-divider"></div>
                                                            <form method="POST" action="{{ route('staffings.destroy', $staffing->id) }}" 
                                                                  onsubmit="return confirm('Careful! This will permanently delete the staffing for: {{ $staffing->instance->event->title }}')">
                                                                @method('DELETE')
                                                                @csrf
                                                                <button class="dropdown-item text-danger" type="submit">
                                                                    <i class="fas fa-trash fa-sm fa-fw mr-2"></i> Delete Staffing
                                                                </button>
                                                            </form>
                                                        @endcan
                                                    </div>
                                                </div>
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