@extends('layouts.app')
@section('title', 'Calendars')
@section('title-flex')
    @can('create', \App\Models\Calendar::class)
        <a href="{{ route('calendars.create') }}" class="btn btn-sm btn-success btn-icon-split">
            <span class="icon text-white-50">
                <i class="fas fa-plus"></i>
            </span>
            <span class="text">Create Calendar</span>
        </a>
    @endcan
@endsection
@section('content')
    <div class="row">
        <div class="col-xl-12 col-md-12 mb-12">
            <div class="card shadow mb-4">
                <div class="card-header bg-primary py-3 d-flex flex-row align-items-center justify-content-center">
                    <h6 class="m-0 fw-bold text-white">Calendar Overview</h6> 
                </div>

                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-striped table-sm table-hover table-leftpadded mb-0" width="100%" cellspacing="0" data-page-size="15" data-toggle="table" data-pagination="true" data-filter-control="true" data-sort-reset="true">
                            <thead class="table-light">
                                <tr>
                                    <th data-field="id" data-sortable="true" data-filter-control="input">ID</th>
                                    <th data-field="title" data-sortable="true" data-filter-control="input">Name</th>
                                    <th data-field="actions" data-sortable="true" data-filter-control="input">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($calendars as $calendar)
                                    @can('view', $calendar)
                                        <tr>
                                            <td>{{ $calendar->id }}</td>
                                            <td>{{ $calendar->name }}</td>
                                            <td>
                                                <a class="btn btn-info" href="{{ route('calendar', $calendar->id) }}"><i class="fas fa-eye"></i>
                                                    Show</a>
                                                @can('update', $calendar)
                                                    <a class="btn btn-primary" href="{{ route('calendars.edit', $calendar->id) }}"><i class="fas fa-edit"></i>
                                                        Edit</a>
                                                @endcan
                                                @can('destroy', $calendar)
                                                    <form method="POST" action="{{ route('calendars.destroy', $calendar->id) }}" style="display:inline"
                                                        onsubmit="return confirm('Are you sure you want to delete this calendar?')">
                                                        @method('DELETE')
                                                        @csrf
                                                        <button class="btn btn-danger" type="submit"><i class="fas fa-trash"
                                                                aria-hidden="true"></i> Delete</button>
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