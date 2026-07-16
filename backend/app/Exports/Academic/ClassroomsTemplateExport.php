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
        return [
            ['X-IPA-1', 'X IPA 1', 'X', 'IPA', 'R101', 30, 'ya'],
            ['X-IPS-1', 'X IPS 1', 'X', 'IPS', 'R102', 32, 'ya'],
        ];
    }
}
