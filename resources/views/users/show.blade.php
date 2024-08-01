@extends('layouts.app')
@section('title', 'User Details')
@section('content')
    <div class="row">
        <div class="col-xl-3 col-md-4 col-sm-12 mb-12">
            <div class="card shadow mb-4">
                <div class="card-header bg-primary py-3 d-flex flex-row align-items-center justify-content-between">
                    <h6 class="m-0 fw-bold text-white">
                        <i class="fas fa-user"></i>&nbsp;{{ $user->first_name.' '.$user->last_name }}
                    </h6>
                </div>
                <div class="card-body">
    
                    <dl class="copyable">
                        <dt>VATSIM ID</dt>
                        <dd>
                            {{ $user->id }}
                            <button type="button" onclick="navigator.clipboard.writeText('{{ $user->id }}')"><i class="fas fa-copy"></i></button>
                        </dd>
    
                        <dt>Name</dt>
                        <dd>{{ $user->first_name.' '.$user->last_name }}<button type="button" onclick="navigator.clipboard.writeText('{{ $user->first_name.' '.$user->last_name }}')"><i class="fas fa-copy"></i></button></dd>
    
                        <dt>Email</dt>
                        <dd class="separator pb-3">{{ $user->email }}<button type="button" onclick="navigator.clipboard.writeText('{{ $user->email }}')"><i class="fas fa-copy"></i></button></dd>
    
                        <dt class="pt-2">Last login</dt>
                        <dd>{{ \Carbon\Carbon::parse($user->last_login)->format('d-m-Y H:i') }}</dd>
                    </dl>
                </div>
            </div>
        </div>
        <div class="col-xl-9 col-md-8 col-sm-12 mb-12">
            <div class="row">
                <div class="col-xl-12 col-lg-12 col-md-12 mb-12 p-0">
                    <div class="card shadow mb-4">
                        <div class="card-header bg-primary py-3 d-flex flex-row align-items-center justify-content-between">
                            <h6 class="m-0 fw-bold text-white">
                                Events
                            </h6>
                        </div>
                        <div class="card-body">
                            @if($user->events()->count() == 0)
                                <p class="mb-0">No registered Events</p>
                            @else
                                <div class="table-responsive">
                                    <table class="table table-sm table-leftpadded mb-0" width="100%" cellspacing="0" data-page-size="10" data-toggle="table" data-pagination="true" data-filter-control="true" data-sort-reset="true">
                                        <thead class="table-light">
                                            <tr>
                                                <th data-field="title" data-sortable="true" data-filter-control="input">Title</th>
                                                <th data-field="calendar" data-sortable="true" data-filter-control="input">Calendar</th>
                                                <th data-field="area" data-sortable="true" data-filter-control="input">Area</th>
                                                <th data-field="startdate" data-sortable="true" data-filter-control="input">Start Date</th>
                                                <th data-field="enddate" data-sortable="true" data-filter-control="input">End Date</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @foreach($events as $event)
                                                <tr>
                                                    <td>
                                                        <a href="{{ route('events.show', $event) }}">{{ $event->title }}</a>
                                                    </td>
                                                    <td>
                                                        @can('view', $event->calendar)
                                                            <a href="{{ route('calendar', $event->calendar) }}">{{ $event->calendar->name }}</a>
                                                        @else
                                                            {{ $event->calendar->name }}
                                                        @endcan 
                                                    </td>
                                                    <td>
                                                        {{ $event->area->name }}
                                                    </td>
                                                    <td>
                                                        {{ \Carbon\Carbon::parse($event->start_date)->format('d-m-Y H:i') }}
                                                    </td>
                                                    <td>
                                                        {{ \Carbon\Carbon::parse($event->end_date)->format('d-m-Y H:i') }}
                                                    </td>
                                                </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                </div>
                            @endif
                        </div>
                    </div>
                </div>
                @can('viewAccess', $user)
                    <div class="col-xl-12 col-lg-12 col-md-12 mb-12 p-0">
                        <div class="card shadow mb-4">
                            <div class="card-header bg-primary py-3 d-flex flex-row align-items-center justify-content-between">
                                <h6 class="m-0 fw-bold text-white">
                                    Access
                                </h6>
                            </div>
                            <div class="card-body">
                                <form action="{{ route('users.update', $user) }}" method="POST">
                                    @method('PATCH')
                                    @csrf
                                    <p>Select none, one or multiple permissions for the user.</p>
                                    <table class="table table-bordered table-hover table-responsive w-100 d-block d-md-table">
                                        <thead>
                                            <tr>
                                                <th>Area</th>
                                                @foreach($groups as $group)
                                                    <th class="text-center">{{ $group->name }} <i class="fas fa-question-circle text-gray-400" title="{{ $group->description }}"></i></th>
                                                @endforeach
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @foreach($areas as $area)
                                                <tr>
                                                    <td>{{ $area->name }}</td>
                                                    @foreach($groups as $group)
                                                        @can('updateGroup', $user)
                                                            <td class="text-center"><input type="checkbox" name="{{ $area->id }}_{{ $group->name }}" {{ $user->groups()->where('group_id', $group->id)->where('area_id', $area->id)->count() ? "checked" : "" }}></td>
                                                        @else
                                                            <td class="text-center"><input type="checkbox" {{ $user->groups()->where('group_id', $group->id)->where('area_id', $area->id)->count() ? "checked" : "" }} disabled></td>
                                                        @endcan
                                                    @endforeach 
                                                </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                    @can('update', $user)
                                        <div class="mb-3">
                                            <button type="submit" class="btn btn-primary">Save access</button>
                                        </div>
                                    @endcan
                                </form>
                            </div>
                        </div>
                    </div>
                @endcan
            </div>
        </div>
    </div>
@endsection
@section('js')
    @vite(['resources/js/bootstrap-table.js'])
@endsection