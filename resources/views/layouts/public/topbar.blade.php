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
            @if (Route::has('login'))
                @auth
                    <li class="nav-item">
                        <a class="nav-link" href="{{ route('dashboard') }}">
                            <i class="fas fa-fw fa-table-columns"></i>
                            <span>Dashboard</span></a>
                    </li>
                @else
                    <li class="nav-item">
                        <a class="nav-link" href="{{ route('login') }}">
                            <span>Login</span></a>
                    </li>
                @endauth
            @endif
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
        </ul>
    </div>
</nav>