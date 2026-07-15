<?php

namespace App\Domain\Attendance\Enums;

enum AttendanceStatus: string
{
    case Hadir = 'hadir';
    case Sakit = 'sakit';
    case Izin = 'izin';
    case TanpaKeterangan = 'tanpa_keterangan';
    case Alfa = 'alfa';
    case BelumScan = 'belum_scan'; // Virtual status, not stored in DB

    public function label(): string
    {
        return match ($this) {
            self::Hadir => 'Hadir',
            self::Sakit => 'Sakit',
            self::Izin => 'Izin',
            self::TanpaKeterangan => 'Tanpa Keterangan',
            self::Alfa => 'Alfa',
            self::BelumScan => 'Belum Scan',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Hadir => 'green',
            self::Sakit => 'yellow',
            self::Izin => 'blue',
            self::TanpaKeterangan => 'orange',
            self::Alfa => 'red',
            self::BelumScan => 'gray',
        };
    }

    /**
     * Get all statuses that can be stored in database
     */
    public static function storable(): array
    {
        return [
            self::Hadir,
            self::Sakit,
            self::Izin,
            self::TanpaKeterangan,
            self::Alfa,
        ];
    }

    /**
     * Check if this status counts as absent
     */
    public function isAbsent(): bool
    {
        return in_array($this, [self::Alfa, self::TanpaKeterangan]);
    }

    /**
     * Check if this status is excused
     */
    public function isExcused(): bool
    {
        return in_array($this, [self::Sakit, self::Izin]);
    }
}
