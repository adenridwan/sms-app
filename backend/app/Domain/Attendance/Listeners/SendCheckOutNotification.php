<?php

namespace App\Domain\Attendance\Listeners;

use App\Domain\Attendance\Events\StudentCheckedOut;
use App\Domain\Attendance\Events\TeacherCheckedOut;
use App\Domain\Notification\Services\NotificationDispatcher;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Log;

class SendCheckOutNotification implements ShouldQueue
{
    public int $tries = 3;
    public int $backoff = 10;

    public function __construct(
        private NotificationDispatcher $dispatcher
    ) {}

    /**
     * Handle student check-out event
     */
    public function handleStudentCheckOut(StudentCheckedOut $event): void
    {
        try {
            $time = $event->attendance->check_out_time?->format('H:i');

            $this->dispatcher->dispatchStudentCheckOut(
                $event->student,
                $time ?? now()->format('H:i')
            );
        } catch (\Exception $e) {
            Log::error('SendCheckOutNotification: Failed for student', [
                'student_id' => $event->student->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Handle teacher check-out event
     */
    public function handleTeacherCheckOut(TeacherCheckedOut $event): void
    {
        try {
            $time = $event->attendance->check_out_time?->format('H:i');

            $this->dispatcher->dispatchTeacherCheckOut(
                $event->teacher,
                $time ?? now()->format('H:i')
            );
        } catch (\Exception $e) {
            Log::error('SendCheckOutNotification: Failed for teacher', [
                'teacher_id' => $event->teacher->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Handle the event based on type
     */
    public function handle(StudentCheckedOut|TeacherCheckedOut $event): void
    {
        if ($event instanceof StudentCheckedOut) {
            $this->handleStudentCheckOut($event);
        } else {
            $this->handleTeacherCheckOut($event);
        }
    }
}
