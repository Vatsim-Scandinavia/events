<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use Inertia\Inertia;
use Inertia\Response;

class ProfileController extends Controller
{
    public function edit(): Response
    {
        return Inertia::render('settings/profile');
    }
}
