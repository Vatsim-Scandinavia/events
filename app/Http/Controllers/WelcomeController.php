<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class WelcomeController extends Controller
{
    /**
     * Show the landing page if not logged in, or redirect if logged in.
     *
     * @return \Illuminate\View\View
     */
    public function index()
    {
        if(Auth::check()) {
            return redirect()->intended(route('dashboard'));
        }

        return view('welcome');
    }
}
