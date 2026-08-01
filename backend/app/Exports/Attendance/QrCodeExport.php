<?php

namespace App\Exports\Attendance;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

/**
 * Export A — daftar kode QR/RFID (Excel/CSV) untuk pembuatan kartu di vendor.
 */
class QrCodeExport implements FromCollection, WithHeadings, WithMapping
{
    /**
     * @param Collection<int, array> $rows  hasil QrExportService
     */
    public function __construct(private Collection $rows) {}

    public function collection(): Collection
    {
        return $this->rows;
    }

    public function headings(): array
    {
        return ['Tipe', 'NIS/NIP', 'Nama', 'Kelas', 'Kode QR', 'Kode RFID'];
    }

    /**
     * @param array $row
     */
    public function map($row): array
    {
        return [
            $row['kind'] === 'teacher' ? 'Guru' : 'Siswa',
            $row['identifier'],
            $row['name'],
            $row['classroom'],
            $row['unique_code'],
            $row['rfid_code'],
        ];
    }
}
