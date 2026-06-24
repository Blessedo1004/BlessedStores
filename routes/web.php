<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\PaymentController;
use App\Http\Controllers\Auth\AuthController;


Route::get('/', function () {
    return view('home');
})->name('home');

Route::livewire('/signup', 'auth.⚡signup')->name('signUp');

Route::middleware('cache.headers:no_store,private')->controller(AuthController::class)->group(function(){
    Route::get('/login','showSignIn')->name('login');
    Route::post('/login','signIn')->name('auth.signin');
});

Route::group(['middleware' => ['auth', 'cache.headers:no_store,private']], function () {
   Route::livewire('/dashboard', '⚡dashboard')->name('dashboard');
});

Route::get('/payment', [PaymentController::class, 'showForm'])->name('payment.form');
Route::post('/pay', [PaymentController::class, 'initialize'])->name('payment.initialize');
Route::get('/payment/callback', [PaymentController::class, 'callback'])->name('payment.callback');