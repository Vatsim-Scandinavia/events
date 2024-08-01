<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    @include('layouts.header')
</head>
<body>
    @include('layouts.topbar')

    <div class="container mt-5">
        <div class="row">
            <div class="col-lg-12 margin-tb">
                <div class="d-sm-flex align-items-center justify-content-between mb-4">
                    <h1 class="h3 mb-0 text-gray-800">@yield('title', 'Page Title')</h1>
                    @yield('title-flex')
                </div>
            </div>
        </div>

        @if(Session::has('success') OR isset($success))
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <button type="button" class="close" data-dismiss="alert">&times;</button>
                {!! Session::pull("success") !!}
            </div>
        @endif

        @if ($errors->any())
            <div class="alert alert-danger">
                @if(count($errors) > 1)
                    <ul>
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                @else
                    {{ $errors->first() }}
                @endif
            </div>
        @endif
        
        @yield('content')
    </div>

    @vite(['resources/js/app.js'])
    @yield('js')
</body>
</html>