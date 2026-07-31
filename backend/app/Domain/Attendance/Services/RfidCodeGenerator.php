<?php

namespace App\Domain\Attendance\Services;

use App\Infrastructure\Persistence\Eloquent\Student\Student;
use App\Infrastructure\Persistence\Eloquent\Teacher\Teacher;
use Illuminate\Database\Eloquent\Builder;
use RuntimeException;

/**
 * Pembuat kode RFID terbitan sistem — dipakai bila sekolah menerbitkan
 * sendiri kartunya (kartu writable / kode dicetak), bukan memakai UID bawaan
 * kartu yang dibaca reader.
 *
 * Kode sengaja ACAK, bukan turunan NIP/tanggal lahir: rfid_code adalah
 * kredensial kehadiran (AttendanceScanService mencocokkan satu string ke
 * unique_code ATAU rfid_code), jadi kode yang bisa ditebak dari data pegawai
 * membuka jalan titip absen. Alasan lain: NIP boleh kosong, dan data pribadi
 * tidak perlu tercetak di kartu.
 *
 * Prefiks mengikuti gaya unique_code (TCH-/STU-) sekaligus memastikan kode
 * terbitan sistem tidak pernah bentrok dengan kode QR.
 */
class RfidCodeGenerator
{
    public const TEACHER_PREFIX = 'RFT-';

    public const STUDENT_PREFIX = 'RFS-';

    /** Tanpa 0/O dan 1/I supaya tidak salah baca saat diketik ulang. */
    private const ALPHABET = '23456789ABCDEFGHJKLMNPQRSTUVWXYZ';

    private const LENGTH = 8;

    private const MAX_ATTEMPTS = 10;

    public function forTeacher(Teacher $teacher): string
    {
        return $this->generate($teacher->tenant_id, self::TEACHER_PREFIX);
    }

    public function forStudent(Student $student): string
    {
        return $this->generate($student->tenant_id, self::STUDENT_PREFIX);
    }

    public function generate(string $tenantId, string $prefix): string
    {
        for ($attempt = 0; $attempt < self::MAX_ATTEMPTS; $attempt++) {
            $code = $prefix . $this->randomPart();

            if (! $this->isTaken($tenantId, $code)) {
                return $code;
            }
        }

        throw new RuntimeException('Gagal membuat kode RFID unik, silakan coba lagi.');
    }

    private function randomPart(): string
    {
        $alphabet = self::ALPHABET;
        $lastIndex = strlen($alphabet) - 1;
        $code = '';

        for ($i = 0; $i < self::LENGTH; $i++) {
            $code .= $alphabet[random_int(0, $lastIndex)];
        }

        return $code;
    }

    /**
     * Bentrok bila kode sudah dipakai sebagai rfid_code MAUPUN unique_code
     * siswa/guru mana pun — keduanya dicocokkan saat scan.
     */
    private function isTaken(string $tenantId, string $code): bool
    {
        $matches = fn (Builder $query) => $query->where(
            fn (Builder $q) => $q->where('rfid_code', $code)->orWhere('unique_code', $code)
        );

        return Student::where('tenant_id', $tenantId)->tap($matches)->exists()
            || Teacher::where('tenant_id', $tenantId)->tap($matches)->exists();
    }
}
