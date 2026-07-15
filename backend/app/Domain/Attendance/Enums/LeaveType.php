<?php

namespace App\Domain\Attendance\Enums;

enum LeaveType: string
{
    case Sakit = 'sakit';
    case Izin = 'izin';

    public function label(): string
    {
        return match ($this) {
            self::Sakit => 'Sakit',
            self::Izin => 'Izin',
        };
    }

    public function toAttendanceStatus(): AttendanceStatus
    {
        return match ($this) {
            self::Sakit => AttendanceStatus::Sakit,
            self::Izin => AttendanceStatus::Izin,
        };
    }
}
