<?php

namespace App\Domain\Attendance\Enums;

enum ScanType: string
{
    case Masuk = 'masuk';
    case Pulang = 'pulang';

    public function label(): string
    {
        return match ($this) {
            self::Masuk => 'Masuk',
            self::Pulang => 'Pulang',
        };
    }

    public function isCheckIn(): bool
    {
        return $this === self::Masuk;
    }

    public function isCheckOut(): bool
    {
        return $this === self::Pulang;
    }
}
