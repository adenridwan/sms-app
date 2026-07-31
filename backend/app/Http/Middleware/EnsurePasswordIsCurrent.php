<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Paksa pengguna dengan penanda `must_change_password` (password awal
 * auto-generate atau habis di-reset admin) untuk mengganti password
 * sebelum bisa mengakses halaman lain (TEACHER-MODULE-PLAN.md §2).
 *
 * Diterapkan pada grup route web yang butuh sesi aktif; route
 * '/change-password' dan logout SENGAJA didaftarkan di luar grup ini
 * supaya tidak terjadi redirect loop.
 */
class EnsurePasswordIsCurrent
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->user()?->mustChangePassword()) {
            return redirect()->route('change-password');
        }

        return $next($request);
    }
}
