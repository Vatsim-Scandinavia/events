<nav class="navbar navbar-expand-lg navbar-dark bg-primary">
    <a class="navbar-brand" href="/">
        <img src="{{ asset('images/' . config('app.logo')) }}" alt="logo" class="d-inline-block align-top" width="30" height="30">
        {{ config('app.owner_name') }} Events
    </a>
    <button class="navbar-toggler" type="button" data-toggle="collapse" data-target="#navbarNav" aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
        <span class="navbar-toggler-icon"></span>
    </button>
    <div class="collapse navbar-collapse" id="navbarNav">
        <ul class="navbar-nav ml-auto">
            <li class="nav-item">
                <a class="nav-link {{ Route::is('home') ? 'active' : '' }}" href="{{ route('home') }}">
                    <i class="fas fa-fw fa-house"></i>
                    <span>Home</span></a>
            </li>
            @if (Route::has('login'))
                @guest
                    <li class="nav-item">
                        <a class="nav-link" href="{{ route('login') }}">
                            <span>Login</span></a>
                    </li>
                @endauth
            @endif
            @auth
                @if (\Auth::user()->isModeratorOrAbove())
                    @can('index', \App\Models\Calendar::class)
                        <li class="nav-item">
                            <a class="nav-link {{ Route::is('calendars.index') || Route::is('calendars.create') || Route::is('calendars.edit') ? 'active' : '' }}" href="{{ route('calendars.index') }}">
                                <i class="fas fa-fw fa-calendar-alt"></i>
                                <span>Calendars</span>
                            </a>
                        </li>
                    @endcan
                    @can('index', \App\Models\Event::class)
                        <li class="nav-item {{ Route::is('events.index') || Route::is('events.create') | Route::is('events.edit')? 'active' : '' }}">
                            <a class="nav-link" href="{{ route('events.index') }}">
                                <i class="fas fa-fw fa-calendar-day"></i>
                                <span>Events</span>
                            </a>
                        </li>
                    @endcan
                @endif
                @if (\Auth::user()->isAdmin())
                    <li class="nav-item {{ Route::is('users.index') || Route::is('users.show') ? 'active' : '' }}">
                        <a class="nav-link" href="{{ route('users.index') }}">
                            <i class="fas fa-fw fa-users"></i>
                            <span>User Management</span>
                        </a>
                    </li>
                @endif
            @endauth
            @foreach (App\Models\Calendar::where('public', 1)->get()->take(4) as $calendar)
                <li class="nav-item {{ Route::is('events.show') && request()->route('event')->calendar_id == $calendar->id || Route::is('calendar') && request()->route('calendar')->id == $calendar->id ? 'active' : '' }}">
                    <a class="nav-link" href="{{ route('calendar', $calendar->id) }}">
                        <i class="fas fa-fw fa-calendar"></i>
                        <span>{{ $calendar->name }}</span></a>
                </li>
            @endforeach
            @auth
                <li class="nav-item">
                    <a class="nav-link" href="{{ route('logout') }}">
                        <i class="fas fa-fw fa-sign-out-alt"></i>
                        <span>Logout</span></a>
                </li>
            @endauth
            @env('local')
                @guest
                    <li class="nav-item">
                        <a class="nav-link" href="#" data-toggle="modal" data-target="#devloginSidebar">
                            <i class="fa fa-lg fa-key"></i>&nbsp;Login as user
                        </a>
                    </li>
                @endguest
            @endenv
        </ul>
    </div>
</nav>

@env('local')
    @guest
        <!-- Sidebar Modal -->
        <div class="modal fade right" id="devloginSidebar" tabindex="-1" role="dialog" aria-labelledby="devloginSidebarLabel" aria-hidden="true">
            <div class="modal-dialog modal-full-height modal-right" role="document">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="devloginSidebarLabel">Login as user</h5>
                        <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                            <span aria-hidden="true">&times;</span>
                        </button>
                    </div>
                    <div class="modal-body">
                        <div class="alert alert-warning" role="alert">
                            This function is only available in local environment.
                        </div>
                        <div class="d-flex flex-row flex-wrap gap-2 justify-content-between">
                            <x-login-link key="10000001" label="10000001" />
                            <x-login-link key="10000002" label="10000002" />
                            <x-login-link key="10000003" label="10000003" />
                            <x-login-link key="10000004" label="10000004" />
                            <x-login-link key="10000005" label="10000005" />
                            <x-login-link key="10000006" label="10000006" />
                            <x-login-link key="10000007" label="10000007" />
                            <x-login-link key="10000008" label="10000008" />
                            <x-login-link key="10000009" label="10000009" />
                            <x-login-link key="10000010" label="10000010" />
                        </div>
                    </div>
                </div>
            </div>
        </div>
    @endguest
@endenv