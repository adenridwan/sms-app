<?php

use App\Http\Controllers\Web\PageController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
*/

// Public Routes
Route::get('/', function () {
    return redirect('/login');
})->name('home');

// Auth Routes (Guest only)
Route::middleware('guest')->group(function () {
    Route::get('/login', [PageController::class, 'login'])->name('login');
    Route::get('/register', [PageController::class, 'register'])->name('register');
});

// Authenticated Routes
Route::middleware(['auth'])->group(function () {
    // Dashboard
    Route::get('/dashboard', [PageController::class, 'dashboard'])->name('dashboard');

    // Students Module
    Route::prefix('students')->name('students.')->group(function () {
        Route::get('/', [PageController::class, 'students'])->name('index');
        Route::get('/create', [PageController::class, 'createStudent'])->name('create');
        Route::get('/{student}', [PageController::class, 'students'])->name('show');
        Route::get('/{student}/edit', [PageController::class, 'students'])->name('edit');
    });

    // Teachers Module
    Route::prefix('teachers')->name('teachers.')->group(function () {
        Route::get('/', [PageController::class, 'teachers'])->name('index');
        Route::get('/create', [PageController::class, 'teachers'])->name('create');
        Route::get('/{teacher}', [PageController::class, 'teachers'])->name('show');
        Route::get('/{teacher}/edit', [PageController::class, 'teachers'])->name('edit');
    });

    // Academic Module
    Route::prefix('academic')->name('academic.')->group(function () {
        Route::get('/years', [PageController::class, 'academicYears'])->name('years');
        Route::get('/schedules', [PageController::class, 'schedules'])->name('schedules');
    });

    // Master Data Module
    Route::prefix('master')->name('master.')->group(function () {
        Route::get('/class-rooms', [PageController::class, 'classRooms'])->name('class-rooms');
        Route::get('/subjects', [PageController::class, 'subjects'])->name('subjects');
    });

    // Finance Module
    Route::prefix('finance')->name('finance.')->group(function () {
        Route::get('/payments', [PageController::class, 'payments'])->name('payments');
    });

    // Attendance Module
    Route::get('/attendance', [PageController::class, 'attendance'])->name('attendance');

    // Settings Module
    Route::prefix('settings')->name('settings.')->group(function () {
        Route::get('/', [PageController::class, 'settings'])->name('index');
        Route::get('/users', [PageController::class, 'users'])->name('users');
        Route::get('/class-rooms', [PageController::class, 'settingsClassRooms'])->name('class-rooms');
        Route::get('/majors', [PageController::class, 'settingsMajors'])->name('majors');
    });

    // Profile Routes
    Route::get('/profile', function () {
        return Inertia::render('Profile/Edit');
    })->name('profile.edit');
});

// Fallback: elegant 404 page for unknown routes
Route::fallback(function () {
    return Inertia::render('Error', ['status' => 404]);
});
