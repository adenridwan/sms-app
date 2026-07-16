<?php

namespace App\Exports\Academic;

use App\Infrastructure\Persistence\Eloquent\Academic\Major;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class MajorsExport implements FromCollection, WithHeadings, WithMapping
{
    public function collection(): Collection
    {
        return Major::orderBy('code')->get();
    }

    public function headings(): array
    {
        return ['kode', 'nama', 'deskripsi', 'aktif'];
    }

    /**
     * @param Major $major
     */
    public function map($major): array
    {
        return [
            $major->code,
            $major->name,
            $major->description,
            $major->is_active ? 'ya' : 'tidak',
        ];
    }
}
