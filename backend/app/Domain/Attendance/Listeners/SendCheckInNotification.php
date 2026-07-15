<?php

namespace App\Domain\Attendance\Listeners;

use App\Domain\Attendance\Events\StudentCheckedIn;
use App\Domain\Attendance\Events\TeacherCheckedIn;
use App\Domain\Notification\Services\NotificationDispatcher;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Log;

class SendCheckInNotification implements ShouldQueue
{
    public int $tries = 3;
    public int $backoff = 10;

    public function __construct(
        private NotificationDispatcher $dispatcher
    ) {}

    /**
     * Handle student check-in event
     */
    public function handleStudentCheckIn(StudentCheckedIn $event): void
    {
        try {
            $time = $event->attendance->check_in_time?->format('H:i');

            $this->dispatcher->dispatchStudentCheckIn(
                $event->student,
                $time ?? now()->format('H:i'),
                $event->lateMinutes
            );
        } catch (\Exception $e) {
            Log::error('SendCheckInNotification: Failed for student', [
                'student_id' => $event->student->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Handle teacher check-in event
     */
    public function handleTeacherCheckIn(TeacherCheckedIn $event): void
    {
        try {
            $time = $event->attendance->check_in_time?->format('H:i');

            $this->dispatcher->dispatchTeacherCheckIn(
                $event->teacher,
                $time ?? now()->format('H:i'),
                $event->lateMinutes
            );
        } catch (\Exception $e) {
            Log::error('SendCheckInNotification: Failed for teacher', [
                'teacher_id' => $event->teacher->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Handle the event based on type
     */
    public function handle(StudentCheckedIn|TeacherCheckedIn $event): void
    {
        if ($event instanceof StudentCheckedIn) {
            $this->handleStudentCheckIn($event);
        } else {
            $this->handleTeacherCheckIn($event);
        }
    }
}
