@extends('layouts.app')

@section('title', 'Dashboard')
@section('title-flex')
    @can('create', Event::class)
        <a href="{{ route('staffings.create') }}" class="btn btn-sm btn-success btn-icon-split">
            <span class="icon text-white-50">
                <i class="fas fa-plus"></i>
            </span>
            <span class="text">Create Staffing</span>
        </a>
    @endcan
@endsection
@section('content')
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-md-8">
                <div class="card shadow mb-4 d-none d-xl-block d-lg-block d-md-block">
                    <div class="card-header bg-primary py-3 d-flex flex-row align-items-center justify-content-between">
                        <h6 class="m-0 font-weight-bold text-white">Available Staffings</h6>
                    </div>
                    <div class="card-body">
                        
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
