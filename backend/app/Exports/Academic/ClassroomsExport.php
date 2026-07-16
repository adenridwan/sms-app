<?php

namespace App\Exports\Academic;

use App\Infrastructure\Persistence\Eloquent\Academic\Classroom;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class ClassroomsExport implements FromCollection, WithHeadings, WithMapping
{
    public function collection(): Collection
    {
        return Classroom::with(['gradeLevel', 'major', 'academicYear'])
            ->orderBy('code')
            ->get();
    }

    public function headings(): array
    {
        return ['kode', 'nama', 'tingkat', 'jurusan', 'ruangan', 'kapasitas', 'aktif'];
    }

    /**
     * @param Classroom $classroom
     */
    public function map($classroom): array
    {
        return [
            $classroom->code,
            $classroom->name,
            $classroom->gradeLevel?->code,
            $classroom->major?->code,
            $classroom->room,
            $classroom->capacity,
            $classroom->is_active ? 'ya' : 'tidak',
        ];
    }
}
