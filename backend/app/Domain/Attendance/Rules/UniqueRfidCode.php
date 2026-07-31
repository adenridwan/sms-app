<?php

namespace App\Domain\Attendance\Rules;

use App\Infrastructure\Persistence\Eloquent\Student\Student;
use App\Infrastructure\Persistence\Eloquent\Teacher\Teacher;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Database\Eloquent\Builder;

/**
 * rfid_code harus unik lintas tabel students+teachers dalam satu tenant
 * (APPLICATION_SPEC.md §4.6) — tidak ada unique constraint DB untuk ini
 * karena kolomnya tersebar di dua tabel berbeda.
 *
 * Kolom unique_code (QR) ikut diperiksa: saat absensi, satu string yang sama
 * dicocokkan ke unique_code ATAU rfid_code (Student/Teacher::findByCode),
 * jadi kode RFID yang kebetulan sama dengan QR orang lain akan membuat scan
 * mengarah ke orang yang salah.
 */
class UniqueRfidCode implements ValidationRule
{
    public function __construct(
        private string $tenantId,
        private ?string $excludeStudentId = null,
        private ?string $excludeTeacherId = null,
    ) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $matches = fn (Builder $query) => $query->where(
            fn (Builder $q) => $q->where('rfid_code', $value)->orWhere('unique_code', $value)
        );

        $studentClash = Student::where('tenant_id', $this->tenantId)
            ->tap($matches)
            ->when($this->excludeStudentId, fn ($q) => $q->whereKeyNot($this->excludeStudentId))
            ->exists();

        $teacherClash = ! $studentClash && Teacher::where('tenant_id', $this->tenantId)
            ->tap($matches)
            ->when($this->excludeTeacherId, fn ($q) => $q->whereKeyNot($this->excludeTeacherId))
            ->exists();

        if ($studentClash || $teacherClash) {
            $fail('Kode RFID sudah digunakan siswa/guru lain (termasuk sebagai kode QR).');
        }
    }
}
