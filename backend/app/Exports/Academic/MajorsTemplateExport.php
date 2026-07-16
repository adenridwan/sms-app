<?php

namespace App\Exports\Academic;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;

class MajorsTemplateExport implements FromArray, WithHeadings
{
    public function headings(): array
    {
        return ['kode', 'nama', 'deskripsi', 'aktif'];
    }

    public function array(): array
    {
        return [
            ['IPA', 'Ilmu Pengetahuan Alam', 'Jurusan IPA', 'ya'],
            ['IPS', 'Ilmu Pengetahuan Sosial', 'Jurusan IPS', 'ya'],
        ];
    }
}
