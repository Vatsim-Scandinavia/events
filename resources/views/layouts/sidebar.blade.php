<!-- Sidebar -->
<ul class="navbar-nav sidebar sidebar-dark accordion" id="accordionSidebar">
    <!-- Sidebar - Brand -->
    <a class="sidebar-brand d-flex align-items-center" href="{{-- route('dashboard') --}}">
        <div class="sidebar-brand-icon">
            <img src="{{ asset('images/temp-logo.png') }}">
        </div>

        <div class="sidebar-brand-text mx-3">{{ config('app.name') }}</div>
    </a>

    @auth
        <!-- Divider -->
        <hr class="sidebar-divider my-0">

        <li class="nav-item {{ Route::is('dashboard') ? 'active' : '' }}">
            <a class="nav-link" href="{{ route('dashboard') }}">
                <i class="fas fa-fw fa-table-columns"></i>
                <span>Dashboard</span></a>
        </li>

        <li class="nav-item {{ Route::is('calendar') ? 'active' : '' }}">
            <a class="nav-link" href="{{ route('calendar', 1) }}">
                <i class="fas fa-fw fa-calendar"></i>
                <span>Calendar</span></a>
        </li>

        {{-- <li class="nav-item {{ Route::is('staffings.index') || Route::is('staffings.create') || Route::is('staffings.edit') ? 'active' : '' }}">
            <a class="nav-link" href="{{ route('staffings.index') }}">
                <i class="fas fa-fw fa-calendar"></i>
                <span>Staffings</span></a>
        </li> --}}

        @if (\Auth::user()->isModeratorOrAbove())
            
            <!-- Divider -->
            <hr class="sidebar-divider d-none d-md-block">

            <!-- Headng -->
            <div class="sidebar-heading">
                Events
            </div>
            @if (\Auth::user()->isAdmin())
                <li class="nav-item {{ Route::is('calendars.index') || Route::is('calendars.create') || Route::is('calendars.edit') ? 'active' : '' }}">
                    <a class="nav-link" href="{{ route('calendars.index') }}">
                        <i class="fas fa-fw fa-calendar-alt"></i>
                        <span>Calendar Management</span>
                    </a>
                </li>
            @endif

            <li class="nav-item {{ Route::is('events.index') || Route::is('events.create') ? 'active' : '' }}">
                <a class="nav-link" href="{{ route('events.index') }}">
                    <i class="fas fa-fw fa-calendar-alt"></i>
                    <span>Event Management</span>
                </a>
            </li>
        @endif

        <!-- Divider -->
        <hr class="sidebar-divider d-none d-md-block">

        <!-- Sidebar Toggler (Sidebar) -->
        <div class="text-center d-none d-md-inline">
            <button class="rounded-circle border-0" id="sidebarToggle"></button>
        </div>

        @if(Config::get('app.env') != "production")
            <div class="alert alert-warning" style="font-size: 80%;" role="alert">
                Development Env
            </div>
        @endif

        <!-- Logo -->
        <a href="#"><img class="logo" src="{{-- asset('images/logos/'.Config::get('app.logo')) --}}"></a>
        <a href="https://github.com/Vatsim-Scandinavia/controlcenter" target="_blank" class="version-sidebar">{{ config('app.name') }} v0.0.1</a>
    @else
        <!-- Divider -->
        <hr class="sidebar-divider my-0">

        <li class="nav-item active">
        <a class="nav-link" href="{{ route('login') }}">
            <i class="fas fa-sign-in-alt"></i>
            <span>Login</span></a>
        </li>
    @endauth
</ul>