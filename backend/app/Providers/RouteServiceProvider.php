<?php

namespace App\Providers;

use App\Infrastructure\Persistence\Eloquent\Exam\Exam;
use App\Infrastructure\Persistence\Eloquent\Exam\ExamScore;
use App\Infrastructure\Persistence\Eloquent\Exam\ExamType;
use App\Infrastructure\Persistence\Eloquent\Library\Book;
use App\Infrastructure\Persistence\Eloquent\Library\BookCategory;
use App\Infrastructure\Persistence\Eloquent\Library\BookLoan;
use App\Infrastructure\Persistence\Eloquent\Library\LibraryMember;
use App\Infrastructure\Persistence\Eloquent\Notification\Announcement;
use App\Infrastructure\Persistence\Eloquent\Report\ReportCard;
use App\Infrastructure\Persistence\Eloquent\Student\StudentAchievement;
use App\Infrastructure\Persistence\Eloquent\Student\StudentEnrollment;
use App\Infrastructure\Persistence\Eloquent\Student\StudentGuardian;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Foundation\Support\Providers\RouteServiceProvider as ServiceProvider;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;

class RouteServiceProvider extends ServiceProvider
{
    /**
     * The path to your application's "home" route.
     *
     * @var string
     */
    public const HOME = '/dashboard';

    /**
     * Define your route model bindings, pattern filters, and other route configuration.
     */
    public function boot(): void
    {
        RateLimiter::for('api', function (Request $request) {
            return Limit::perMinute(60)->by($request->user()?->id ?: $request->ip());
        });

        RateLimiter::for('auth', function (Request $request) {
            return Limit::perMinute(5)->by($request->ip());
        });

        RateLimiter::for('uploads', function (Request $request) {
            return Limit::perMinute(10)->by($request->user()?->id ?: $request->ip());
        });

        RateLimiter::for('exports', function (Request $request) {
            return Limit::perMinute(3)->by($request->user()?->id ?: $request->ip());
        });

        // Endpoint yang menerima "access password" (mis. koneksi database) —
        // dibatasi ketat supaya tidak bisa di-brute-force.
        RateLimiter::for('sensitive', function (Request $request) {
            return Limit::perMinute(5)->by($request->user()?->id ?: $request->ip());
        });

        // Route model binding untuk model di namespace non-standar
        Route::model('enrollment', StudentEnrollment::class);
        Route::model('guardian', StudentGuardian::class);
        Route::model('achievement', StudentAchievement::class);
        Route::model('announcement', Announcement::class);

        // Library module
        Route::model('book', Book::class);
        Route::model('category', BookCategory::class);
        Route::model('member', LibraryMember::class);
        Route::model('loan', BookLoan::class);

        // Exam module
        Route::model('exam', Exam::class);
        Route::model('type', ExamType::class);
        Route::model('score', ExamScore::class);

        // Report module
        Route::model('reportCard', ReportCard::class);

        $this->routes(function () {
            // API Routes
            Route::middleware('api')
                ->prefix('api')
                ->group(base_path('routes/api.php'));

            // API V1 Routes
            Route::middleware('api')
                ->prefix('api/v1')
                ->name('api.v1.')
                ->group(base_path('routes/api_v1.php'));

            // Web Routes (termasuk /logout — dulu ada routes/auth.php terpisah
            // untuk ini, tapi file itu tidak menambah apa pun yang benar-benar
            // berfungsi selain logout: /register & /login di sana adalah
            // duplikat path-case yang salah dari punya web.php, dan
            // forgot-password/reset-password/verify-email merender komponen
            // Inertia yang tidak pernah ada. Sudah digabung ke web.php.)
            Route::middleware('web')
                ->group(base_path('routes/web.php'));
        });
    }
}
