<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    @include('layouts.header')
</head>
<body id="page-top">
    <div id="app"></div>

    <div id="wrapper">

        @auth
            @include('layouts.sidebar')
        @endauth
        
        <!-- Content Wrapper -->
        <div id="content-wrapper" class="d-flex flex-column">

            <!-- Main Content -->
            <div id="content">
                @auth
                    @include('layouts.topbar')
                @endauth
            </div>
        </div>
    </div>

    <!-- Begin Page Content -->
    <div class="container-fluid">
        @if(Session::has('success') OR isset($success))
            <div class="alert alert-success" role="alert">
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
</body>
</html>
