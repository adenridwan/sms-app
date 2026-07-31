<?php

namespace App\Domain\Attendance\Enums;

enum AttendanceStatus: string
{
    // Backing values match the `student_attendances.status` CHECK constraint
    // (see migration 0001_01_01_000007_create_attendance_tables.php) and the
    // vocabulary already used by `employee_attendances`. Indonesian labels
    // live only in label()/color() below.
    case Hadir = 'present';
    case Sakit = 'sick';
    case Izin = 'permitted';
    case TanpaKeterangan = 'absent';
    case Alfa = 'alpha';
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

    /**
     * Machine-readable Indonesian key used at API response boundaries
     * (e.g. `status` fields the frontend switches on) — distinct from the
     * DB-facing `.value` (English) and from label() (capitalized display
     * text). Keeps the wire contract the frontend already expects stable.
     */
    public function slug(): string
    {
        return match ($this) {
            self::Hadir => 'hadir',
            self::Sakit => 'sakit',
            self::Izin => 'izin',
            self::TanpaKeterangan => 'tanpa_keterangan',
            self::Alfa => 'alfa',
            self::BelumScan => 'belum_scan',
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

    /**
     * Resolve a case from its Indonesian wire slug (inverse of slug()).
     * The API contract speaks Indonesian in BOTH directions — responses via
     * slug(), and request payloads (storeBulk/update) via this.
     */
    public static function fromSlug(string $slug): ?self
    {
        foreach (self::cases() as $case) {
            if ($case->slug() === $slug) {
                return $case;
            }
        }

        return null;
    }

    /**
     * Indonesian wire slugs of the storable statuses — the allow-list for
     * request validation (matches the frontend AttendanceStatus type).
     */
    public static function storableSlugs(): array
    {
        return array_map(fn (self $case) => $case->slug(), self::storable());
    }

    /**
     * Translate raw DB status counts (English, keyed by status value —
     * accepts an array or a Collection from pluck('total', 'status')) into
     * the Indonesian-keyed summary shape the API has always returned.
     *
     * `late` is a DB-legal legacy/imported value with no dedicated enum
     * case; it counts toward `hadir` here, same as it always has.
     */
    public static function summaryFromRaw($rawCounts): array
    {
        return [
            'hadir' => (int) ($rawCounts['present'] ?? 0) + (int) ($rawCounts['late'] ?? 0),
            'sakit' => (int) ($rawCounts['sick'] ?? 0),
            'izin' => (int) ($rawCounts['permitted'] ?? 0),
            'alfa' => (int) ($rawCounts['absent'] ?? 0) + (int) ($rawCounts['alpha'] ?? 0),
        ];
    }
}
