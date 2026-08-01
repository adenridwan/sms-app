<?php

namespace App\Exports\Academic;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;

class ClassroomsTemplateExport implements FromArray, WithHeadings
{
    public function headings(): array
    {
        return ['kode', 'nama', 'tingkat', 'jurusan', 'ruangan', 'kapasitas', 'aktif'];
    }

    public function array(): array
    {
        // Contoh untuk berbagai jenis sekolah:
        // - SD/MI: tingkat 1, 2, 3, 4, 5, 6
        // - SMP/MTs: tingkat 7, 8, 9 atau VII, VIII, IX
        // - SMA/MA/SMK: tingkat X, XI, XII
        // Tingkat kelas akan dibuat otomatis jika belum ada
        return [
            // Contoh SD/MI
            ['1A', 'Kelas 1A', '1', '', 'R001', 25, 'ya'],
            ['2A', 'Kelas 2A', '2', '', 'R002', 25, 'ya'],
            // Contoh SMA/SMK (hapus jika tidak diperlukan)
            ['X-IPA-1', 'X IPA 1', 'X', 'IPA', 'R101', 30, 'ya'],
            ['X-IPS-1', 'X IPS 1', 'X', 'IPS', 'R102', 32, 'ya'],
        ];
    }
}
