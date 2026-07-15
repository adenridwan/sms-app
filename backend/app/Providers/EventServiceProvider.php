<?php

namespace App\Providers;

use Illuminate\Auth\Events\Registered;
use Illuminate\Auth\Listeners\SendEmailVerificationNotification;
use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Event;

class EventServiceProvider extends ServiceProvider
{
    /**
     * The event to listener mappings for the application.
     *
     * @var array<class-string, array<int, class-string>>
     */
    protected $listen = [
        // Auth Events
        Registered::class => [
            SendEmailVerificationNotification::class,
        ],

        \App\Domain\Auth\Events\UserLoggedIn::class => [
            \App\Domain\Auth\Listeners\UpdateLastLogin::class,
            \App\Domain\Auth\Listeners\LogLoginActivity::class,
        ],

        \App\Domain\Auth\Events\UserLoggedOut::class => [
            \App\Domain\Auth\Listeners\LogLogoutActivity::class,
        ],

        // Student Events
        \App\Domain\Student\Events\StudentCreated::class => [
            \App\Domain\Student\Listeners\SendWelcomeNotification::class,
            \App\Domain\Student\Listeners\CreateStudentAccount::class,
        ],

        \App\Domain\Student\Events\StudentEnrolled::class => [
            \App\Domain\Student\Listeners\GenerateStudentFees::class,
            \App\Domain\Student\Listeners\NotifyParentsEnrollment::class,
        ],

        // Attendance Events
        \App\Domain\Attendance\Events\AttendanceRecorded::class => [
            \App\Domain\Attendance\Listeners\NotifyParentAbsence::class,
            \App\Domain\Attendance\Listeners\UpdateAttendanceSummary::class,
        ],

        // Attendance Scan Events (QR/RFID)
        \App\Domain\Attendance\Events\StudentCheckedIn::class => [
            \App\Domain\Attendance\Listeners\SendCheckInNotification::class,
        ],

        \App\Domain\Attendance\Events\StudentCheckedOut::class => [
            \App\Domain\Attendance\Listeners\SendCheckOutNotification::class,
        ],

        \App\Domain\Attendance\Events\TeacherCheckedIn::class => [
            \App\Domain\Attendance\Listeners\SendCheckInNotification::class,
        ],

        \App\Domain\Attendance\Events\TeacherCheckedOut::class => [
            \App\Domain\Attendance\Listeners\SendCheckOutNotification::class,
        ],

        // Exam Events
        \App\Domain\Exam\Events\ExamScoreSubmitted::class => [
            \App\Domain\Exam\Listeners\CheckRemedialEligibility::class,
            \App\Domain\Exam\Listeners\UpdateStudentGrades::class,
        ],

        // Finance Events
        \App\Domain\Finance\Events\PaymentReceived::class => [
            \App\Domain\Finance\Listeners\UpdateFeeStatus::class,
            \App\Domain\Finance\Listeners\SendPaymentReceipt::class,
            \App\Domain\Finance\Listeners\UpdateFinancialReport::class,
        ],

        \App\Domain\Finance\Events\FeeOverdue::class => [
            \App\Domain\Finance\Listeners\SendOverdueReminder::class,
            \App\Domain\Finance\Listeners\ApplyLateFine::class,
        ],

        // Library Events
        \App\Domain\Library\Events\BookBorrowed::class => [
            \App\Domain\Library\Listeners\UpdateBookAvailability::class,
            \App\Domain\Library\Listeners\SendBorrowConfirmation::class,
        ],

        \App\Domain\Library\Events\BookReturned::class => [
            \App\Domain\Library\Listeners\ProcessBookReturn::class,
            \App\Domain\Library\Listeners\NotifyNextReserver::class,
        ],

        \App\Domain\Library\Events\BookOverdue::class => [
            \App\Domain\Library\Listeners\SendOverdueNotification::class,
            \App\Domain\Library\Listeners\CalculateFine::class,
        ],

        // Notification Events
        \App\Domain\Notification\Events\AnnouncementPublished::class => [
            \App\Domain\Notification\Listeners\SendAnnouncementNotifications::class,
        ],
    ];

    /**
     * Register any events for your application.
     */
    public function boot(): void
    {
        //
    }

    /**
     * Determine if events and listeners should be automatically discovered.
     */
    public function shouldDiscoverEvents(): bool
    {
        return false;
    }
}
