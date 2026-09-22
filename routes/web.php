<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\PaymentController;
use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\WebController;


Route::get('/', [WebController::class,'home'])->name('home');

Route::livewire('/signup', 'auth.⚡signup')->name('signUp');
Route::livewire('/register-store', 'auth.⚡register-store')->name('register-store');
Route::livewire('/preregistration-notice', 'auth.⚡preregistration-notice')->name('preregistration-notice');
Route::livewire('/store-preapplication-notice', 'auth.⚡store-preapplication-notice')->name('store-preapplication-notice');
Route::livewire('/forgot-password', 'auth.⚡forgot-password')->name('forgot-password');
Route::livewire('/forgot-password-verify', 'auth.⚡forgot-password-verify')->name('forgot-password-verify');
Route::livewire('/reset-password', 'auth.⚡reset-password')->name('reset-password');

Route::middleware('cache.headers:no_store,private')->controller(AuthController::class)->group(function(){
    Route::get('/login','showSignIn')->name('login');
    Route::post('/login','signIn')->name('auth.signin')->middleware('throttle:auth');
});

Route::group(['middleware' => ['auth', 'cache.headers:no_store,private', 'auth.session']], function () {
   Route::livewire('/dashboard', '⚡dashboard')->name('dashboard');
   Route::livewire('/stores', 'stores/⚡index')->name('stores')->middleware(['can:admin-super-admin-or-store']);
   Route::livewire('/stores/edit/{slug}', 'stores/⚡edit')->name('stores.edit')->middleware(['can:admin-super-admin-or-store']);
   Route::livewire('/store-applications', 'stores/⚡applications')->name('store-applications')->middleware(['can:admin-or-super-admin']);
   Route::livewire('/admins', 'admins/⚡index')->name('admins')->middleware(['can:super-admin']);
   Route::livewire('/admins/create', 'admins/⚡create')->name('admins.create')->middleware(['can:super-admin']);
   Route::livewire('/admins/edit/{user}', 'admins/⚡edit')->name('admins.edit')->middleware(['can:super-admin']);
   Route::livewire('/profile', '⚡profile')->name('profile');
   Route::livewire('/change-password', '⚡change-password')->name('change-password');
   Route::livewire('/products', 'products/⚡index')->name('products')->middleware(['can:store']);
   Route::livewire('/products/create', 'products/⚡create')->name('products.create')->middleware(['can:store']);
   Route::livewire('/products/edit/{slug}', 'products/⚡edit')->name('products.edit')->middleware(['can:store']);
   Route::livewire('/add-store', 'stores/⚡register-store')->name('stores.register')->middleware(['can:store']);
   Route::livewire('/categories', 'categories/⚡index')->name('categories')->middleware(['can:super-admin']);
   Route::livewire('/categories/create', 'categories/⚡create')->name('categories.create')->middleware(['can:super-admin']);
   Route::livewire('/categories/edit/{slug}', 'categories/⚡edit')->name('categories.edit')->middleware(['can:super-admin']);
});

Route::get('/payment', [PaymentController::class, 'showForm'])->name('payment.form');
Route::post('/pay', [PaymentController::class, 'initialize'])->name('payment.initialize');
Route::get('/payment/callback', [PaymentController::class, 'callback'])->name('payment.callback');