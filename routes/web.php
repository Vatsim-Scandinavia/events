<?php

use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\WelcomeController;
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

//--------------------------------------------------------------------------
// Main page
//--------------------------------------------------------------------------
Route::get('/', [WelcomeController::class, 'index'])->name('welcome');

// Route::get('/', function () {
//     return view('welcome');
// })->name('welcome');

//--------------------------------------------------------------------------
// VATSIM Authentication
//--------------------------------------------------------------------------
Route::get('/login', [LoginController::class, 'login'])->middleware('guest')->name('login');
Route::get('/validate', [LoginController::class, 'validateLogin'])->middleware('guest');
Route::get('/logout', [LoginController::class, 'logout'])->middleware('auth')->name('logout');


// Auth::routes();

Route::middleware(['auth', 'staff'])->group(function() {
    
    Route::get('/dashboard', [App\Http\Controllers\FrontController::class, 'index'])->name('dashboard');

    Route::get('/staffings', [App\Http\Controllers\StaffingController::class, 'index'])->name('staffings.index');
    Route::get('/staffings/create', [App\Http\Controllers\StaffingController::class, 'create'])->name('staffings.create');
    Route::get('/staffings/edit/{staffing}', [App\Http\Controllers\StaffingController::class, 'edit'])->name('staffings.edit');
    Route::post('/staffings/store', [App\Http\Controllers\StaffingController::class, 'store'])->name('staffings.store');
    Route::patch('/staffings/edit/{staffing}', [App\Http\Controllers\StaffingController::class, 'update'])->name('staffings.update');
    Route::delete('/staffings/delete/{staffing}', [App\Http\Controllers\StaffingController::class, 'delete'])->name('staffings.delete');
    
});
