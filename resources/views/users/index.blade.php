@extends('layouts.app')
@section('title', 'User Management')
@section('content')
    <div class="row">
        <div class="col-xl-12 col-md-12 mb-12">
            <div class="card shadow mb-4">
                <div class="card-header bg-primary py-3 d-flex flex-row align-items-center justify-content-center">
                    <h6 class="m-0 fw-bold text-white">Event Overview</h6> 
                </div>

                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-striped table-sm table-hover table-leftpadded mb-0" width="100%" cellspacing="0" 
                            data-page-size="15" data-toggle="table" data-pagination="true" data-filter-control="true" data-sort-reset="true">
                            <thead class="table-light">
                                <tr>
                                    <th data-field="id" data-sortable="true" data-filter-control="input">ID</th>
                                    <th data-field="firstname" data-sortable="true" data-filter-control="input">First Name</th>
                                    <th data-field="lastname" data-sortable="true" data-filter-control="input">Last Name</th>
                                    <th data-field="actions" data-sortable="false" data-filter-control="false">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($users as $user)
                                    @can('view', $user)
                                        <tr>
                                            <td><a href="{{ route('users.show', $user) }}">{{ $user->id }}</a></td>
                                            <td>{{ $user->first_name }}</td>
                                            <td>{{ $user->last_name }}</td>
                                            <td>
                                                <a class="btn btn-sm btn-info" href="{{ route('users.show', $user->id) }}"><i class="fas fa-eye"></i> Show</a>
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