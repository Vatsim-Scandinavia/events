<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    @include('public.header')
</head>
<body>
    @include('public.topbar')

    @yield('content')

    @vite(['resources/js/app.js'])
    @yield('js')
</body>
</html>