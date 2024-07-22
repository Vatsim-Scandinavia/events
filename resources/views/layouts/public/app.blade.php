<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    @include('layouts.public.header')
</head>
<body>
    @include('layouts.public.topbar')

    @yield('content')

    @vite(['resources/js/app.js'])
    @yield('js')
</body>
</html>