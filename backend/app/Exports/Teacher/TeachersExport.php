<?php

namespace App\Exports\Teacher;

use App\Infrastructure\Persistence\Eloquent\Teacher\Teacher;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

/**
 * Export data guru. Kolomnya sengaja sama persis dengan template import
 * (lihat TeachersTemplateExport) supaya file hasil export bisa disunting
 * lalu diimport balik tanpa menyusun ulang kolom.
 *
 * Data yang punya kode/daftar tetap (agama, status kepegawaian, sertifikasi,
 * jenjang pendidikan) sengaja TIDAK ikut: pengisiannya lewat form, bukan
 * lewat file, supaya tidak ada kode salah ketik yang masuk.
 */
class TeachersExport implements FromCollection, WithHeadings, WithMapping
{
    public const HEADINGS = [
        'nama_depan',
        'nama_belakang',
        'email',
        'email_kontak',
        'no_hp',
        'nip',
        'nuptk',
        'jenis_kelamin',
        'tempat_lahir',
        'tanggal_lahir',
        'nik',
        'alamat',
        'tanggal_masuk',
    ];

    public function collection(): Collection
    {
        return Teacher::with(['user.profile'])->orderBy('nip')->get();
    }

    public function headings(): array
    {
        return self::HEADINGS;
    }

    /**
     * @param  Teacher  $teacher
     */
    public function map($teacher): array
    {
        $profile = $teacher->user?->profile;

        return [
            $profile?->first_name,
            $profile?->last_name,
            $teacher->user?->email,
            $teacher->user?->contact_email,
            $teacher->no_hp ?? $profile?->phone,
            $teacher->nip,
            $teacher->nuptk,
            match ($profile?->gender) {
                'male' => 'L',
                'female' => 'P',
                default => null,
            },
            $profile?->birth_place,
            $profile?->birth_date?->format('Y-m-d'),
            $profile?->id_number,
            $profile?->address,
            $teacher->join_date?->format('Y-m-d'),
        ];
    }
}
