<?php

namespace App\Domain\Attendance\Events;

use App\Infrastructure\Persistence\Eloquent\Attendance\StudentAttendance;
use App\Infrastructure\Persistence\Eloquent\Student\Student;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class StudentCheckedOut
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public Student $student,
        public StudentAttendance $attendance
    ) {}
}
