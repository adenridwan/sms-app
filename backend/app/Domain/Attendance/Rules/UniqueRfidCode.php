<?php

namespace App\Domain\Attendance\Rules;

use App\Infrastructure\Persistence\Eloquent\Student\Student;
use App\Infrastructure\Persistence\Eloquent\Teacher\Teacher;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * rfid_code harus unik lintas tabel students+teachers dalam satu tenant
 * (APPLICATION_SPEC.md §4.6) — tidak ada unique constraint DB untuk ini
 * karena kolomnya tersebar di dua tabel berbeda.
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
        $studentClash = Student::where('tenant_id', $this->tenantId)
            ->where('rfid_code', $value)
            ->when($this->excludeStudentId, fn ($q) => $q->whereKeyNot($this->excludeStudentId))
            ->exists();

        $teacherClash = ! $studentClash && Teacher::where('tenant_id', $this->tenantId)
            ->where('rfid_code', $value)
            ->when($this->excludeTeacherId, fn ($q) => $q->whereKeyNot($this->excludeTeacherId))
            ->exists();

        if ($studentClash || $teacherClash) {
            $fail('Kode RFID sudah digunakan siswa/guru lain.');
        }
    }
}
