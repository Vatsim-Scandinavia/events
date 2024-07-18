<nav class="navbar navbar-expand-lg navbar-dark bg-primary">
    <a class="navbar-brand" href="/">Vatsim Scandinavia Events</a>
    <button class="navbar-toggler" type="button" data-toggle="collapse" data-target="#navbarNav" aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
        <span class="navbar-toggler-icon"></span>
    </button>
    <div class="collapse navbar-collapse" id="navbarNav">
        <ul class="navbar-nav ml-auto">
            @auth
                <li class="nav-item">
                    <a class="nav-link" href="{{ route('dashboard') }}">
                        <i class="fas fa-fw fa-table-columns"></i>
                        <span>Dashboard</span></a>
                </li>
            @endauth
            @foreach (App\Models\Calendar::where('public', 1)->get() as $calendar)
                <li class="nav-item {{ Route::is('events.show') && request()->route('event')->calendar_id == $calendar->id || Route::is('calendar') && request()->route('calendar')->id == $calendar->id ? 'active' : '' }}">
                    <a class="nav-link" href="{{ route('calendar', $calendar->id) }}">
                        <i class="fas fa-fw fa-calendar"></i>
                        <span>{{ $calendar->name }}</span></a>
                </li>
            @endforeach
        </ul>
    </div>
</nav>