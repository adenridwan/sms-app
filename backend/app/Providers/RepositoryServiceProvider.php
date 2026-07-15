<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

class RepositoryServiceProvider extends ServiceProvider
{
    /**
     * Repository bindings for dependency injection.
     *
     * @var array<string, string>
     */
    protected array $repositories = [
        // Auth
        \App\Domain\Auth\Repositories\UserRepositoryInterface::class =>
            \App\Infrastructure\Persistence\Repositories\Auth\UserRepository::class,

        // Tenant
        \App\Domain\Tenant\Repositories\TenantRepositoryInterface::class =>
            \App\Infrastructure\Persistence\Repositories\Tenant\TenantRepository::class,

        // Academic
        \App\Domain\Academic\Repositories\AcademicYearRepositoryInterface::class =>
            \App\Infrastructure\Persistence\Repositories\Academic\AcademicYearRepository::class,
        \App\Domain\Academic\Repositories\ClassroomRepositoryInterface::class =>
            \App\Infrastructure\Persistence\Repositories\Academic\ClassroomRepository::class,
        \App\Domain\Academic\Repositories\SubjectRepositoryInterface::class =>
            \App\Infrastructure\Persistence\Repositories\Academic\SubjectRepository::class,
        \App\Domain\Academic\Repositories\ScheduleRepositoryInterface::class =>
            \App\Infrastructure\Persistence\Repositories\Academic\ScheduleRepository::class,

        // Student
        \App\Domain\Student\Repositories\StudentRepositoryInterface::class =>
            \App\Infrastructure\Persistence\Repositories\Student\StudentRepository::class,
        \App\Domain\Student\Repositories\EnrollmentRepositoryInterface::class =>
            \App\Infrastructure\Persistence\Repositories\Student\EnrollmentRepository::class,

        // Teacher
        \App\Domain\Teacher\Repositories\TeacherRepositoryInterface::class =>
            \App\Infrastructure\Persistence\Repositories\Teacher\TeacherRepository::class,

        // Staff
        \App\Domain\Staff\Repositories\StaffRepositoryInterface::class =>
            \App\Infrastructure\Persistence\Repositories\Staff\StaffRepository::class,

        // Attendance
        \App\Domain\Attendance\Repositories\AttendanceRepositoryInterface::class =>
            \App\Infrastructure\Persistence\Repositories\Attendance\AttendanceRepository::class,

        // Exam
        \App\Domain\Exam\Repositories\ExamRepositoryInterface::class =>
            \App\Infrastructure\Persistence\Repositories\Exam\ExamRepository::class,
        \App\Domain\Exam\Repositories\GradeRepositoryInterface::class =>
            \App\Infrastructure\Persistence\Repositories\Exam\GradeRepository::class,

        // Finance
        \App\Domain\Finance\Repositories\PaymentRepositoryInterface::class =>
            \App\Infrastructure\Persistence\Repositories\Finance\PaymentRepository::class,
        \App\Domain\Finance\Repositories\FeeRepositoryInterface::class =>
            \App\Infrastructure\Persistence\Repositories\Finance\FeeRepository::class,

        // Library
        \App\Domain\Library\Repositories\BookRepositoryInterface::class =>
            \App\Infrastructure\Persistence\Repositories\Library\BookRepository::class,
        \App\Domain\Library\Repositories\LoanRepositoryInterface::class =>
            \App\Infrastructure\Persistence\Repositories\Library\LoanRepository::class,

        // Notification
        \App\Domain\Notification\Repositories\NotificationRepositoryInterface::class =>
            \App\Infrastructure\Persistence\Repositories\Notification\NotificationRepository::class,

        // Report
        \App\Domain\Report\Repositories\ReportCardRepositoryInterface::class =>
            \App\Infrastructure\Persistence\Repositories\Report\ReportCardRepository::class,

        // Setting
        \App\Domain\Setting\Repositories\SettingRepositoryInterface::class =>
            \App\Infrastructure\Persistence\Repositories\Setting\SettingRepository::class,
    ];

    /**
     * Register services.
     */
    public function register(): void
    {
        foreach ($this->repositories as $interface => $implementation) {
            $this->app->bind($interface, $implementation);
        }
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        //
    }
}
