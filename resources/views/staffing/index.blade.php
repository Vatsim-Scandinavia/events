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
                        <table class="table table-striped table-sm table-hover table-leftpadded mb-0" width="100%" cellspacing="0" data-page-size="15" data-toggle="table" data-pagination="true" data-filter-control="true" data-sort-reset="true">
                            <thead class="table-light">
                                <tr>
                                    <th data-field="id" data-sortable="true" data-filter-control="input">ID</th>
                                    <th data-field="title" data-sortable="true" data-filter-control="input">Title</th>
                                    <th data-field="date" data-sortable="true" data-filter-control="input">Date</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($staffings as $staffing)
                                    @can('view', $staffing)
                                        <tr>
                                            <td>{{ $staffing->id }}</td>
                                            <td>{{ $staffing->title }}</td>
                                            <td>{{ $staffing->date }}</td>
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