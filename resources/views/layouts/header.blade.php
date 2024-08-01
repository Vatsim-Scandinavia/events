<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta http-equiv="X-UA-Compatible" content="ie=edge">
<title>@yield('title') | {{ config('app.name') }}</title>

<!-- Favicon -->
<link rel="shortcut icon" href="favicon.ico">
<meta name="theme-color" content="#ffffff">
<meta name="robots" content="noindex"> 

<!-- CSS -->
@vite(['resources/sass/app.scss', 'resources/sass/vendor.scss'])
@yield('css')