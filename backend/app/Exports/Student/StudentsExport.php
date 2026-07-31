<?php

namespace App\Exports\Student;

use App\Infrastructure\Persistence\Eloquent\Student\Student;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

/**
 * Export data siswa. Kolomnya sama persis dengan template import
 * (StudentsTemplateExport) supaya file hasil export bisa disunting lalu
 * diimport balik tanpa menyusun ulang kolom.
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
            // Kode kelas pada tahun ajaran aktif (kolom opsional saat import)
            $student->currentClass?->code,
        ];
    }
}
