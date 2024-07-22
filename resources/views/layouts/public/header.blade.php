<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta http-equiv="X-UA-Compatible" content="ie=edge">
<title>@yield('title') | {{ config('app.name') }}</title>

<!-- CSS -->
@vite(['resources/sass/app.scss', 'resources/sass/vendor.scss'])
@yield('css')