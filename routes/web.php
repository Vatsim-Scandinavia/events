<?php

use App\Http\Controllers\Auth\LoginController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "web" middleware group. Make something great!
|
*/

Route::get('/', function () {
    return view('welcome');
});

//--------------------------------------------------------------------------
// VATSIM Authentication
//--------------------------------------------------------------------------
Route::get('/login', [LoginController::class, 'login'])->middleware('guest')->name('login');
Route::get('/validate', [LoginController::class, 'validateLogin'])->middleware('guest');
Route::get('/logout', [LoginController::class, 'logout'])->middleware('auth')->name('logout');


// Auth::routes();

Route::get('/home', [App\Http\Controllers\HomeController::class, 'index'])->name('home');
