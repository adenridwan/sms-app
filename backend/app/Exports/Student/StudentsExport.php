<?php

namespace App\Exports\Student;

use App\Infrastructure\Persistence\Eloquent\Student\Student;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

/**
 * Export data siswa. File hasilnya tetap bisa disunting lalu diimport balik.
 *
 * Kolomnya TIDAK lagi identik dengan template import: export memuat kolom
 * `email` (identitas login hasil generate) karena justru itulah yang perlu
 * dibagikan admin ke siswa, sedangkan template tidak memuatnya karena email
 * login tidak boleh diisi dari file. Import siswa mengabaikan kolom `email`,
 * jadi round-trip tetap aman. Lihat docs/EMAIL-OTOMATIS-AKUN.md.
 *
 * Data berkode/daftar tetap (agama, status siswa) sengaja tidak ikut:
 * pengisiannya lewat form, bukan lewat file.
 */
class StudentsExport implements FromCollection, WithHeadings, WithMapping
{
    public const HEADINGS = [
        'nis',
        'nama_depan',
        'nama_belakang',
        'email',
        'email_kontak',
        'jenis_kelamin',
        'tanggal_lahir',
        'nisn',
        'nik',
        'no_hp',
        'tempat_lahir',
        'alamat',
        'sekolah_asal',
        'kelas',
    ];

    public function collection(): Collection
    {
        return Student::with(['user.profile', 'currentClass'])->orderBy('nis')->get();
    }

    public function headings(): array
    {
        return self::HEADINGS;
    }

    /**
     * @param  Student  $student
     */
    public function map($student): array
    {
        $profile = $student->user?->profile;

        return [
            $student->nis,
            $profile?->first_name,
            $profile?->last_name,
            $student->user?->email,
            $student->user?->contact_email,
            match ($profile?->gender) {
                'male' => 'L',
                'female' => 'P',
                default => null,
            },
            $profile?->birth_date?->format('Y-m-d'),
            $student->nisn,
            $profile?->id_number,
            $profile?->phone,
            $profile?->birth_place,
            $profile?->address,
            $student->previous_school,
            // NAMA kelas pada tahun ajaran aktif, bukan kodenya: kode dibuat
            // otomatis (`KLS01`) dan tidak dikenali admin. Import menerima
            // keduanya, jadi file ini tetap bisa diimpor balik.
            $student->currentClass?->name,
        ];
    }
}
