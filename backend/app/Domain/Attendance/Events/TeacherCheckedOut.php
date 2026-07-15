<?php

namespace App\Domain\Attendance\Events;

use App\Infrastructure\Persistence\Eloquent\Attendance\EmployeeAttendance;
use App\Infrastructure\Persistence\Eloquent\Teacher\Teacher;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class TeacherCheckedOut
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public Teacher $teacher,
        public EmployeeAttendance $attendance
    ) {}
}
