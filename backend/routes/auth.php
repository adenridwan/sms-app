<?php

use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

/*
|--------------------------------------------------------------------------
| Authentication Routes
|--------------------------------------------------------------------------
*/

Route::middleware('guest')->group(function () {
    Route::get('login', function () {
        return Inertia::render('Auth/Login');
    })->name('login');

    Route::get('register', function () {
        return Inertia::render('Auth/Register');
    })->name('register');

    Route::get('forgot-password', function () {
        return Inertia::render('Auth/ForgotPassword');
    })->name('password.request');

    Route::get('reset-password/{token}', function ($token) {
        return Inertia::render('Auth/ResetPassword', ['token' => $token]);
    })->name('password.reset');
});

Route::middleware('auth')->group(function () {
    Route::get('verify-email', function () {
        return Inertia::render('Auth/VerifyEmail');
    })->name('verification.notice');

    Route::post('logout', function () {
        auth()->logout();
        request()->session()->invalidate();
        request()->session()->regenerateToken();

        return redirect('/');
    })->name('logout');
});
