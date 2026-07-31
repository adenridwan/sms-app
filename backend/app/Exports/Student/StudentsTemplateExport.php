<?php

namespace App\Exports\Student;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;

/**
 * Template import siswa — berisi contoh baris agar formatnya terbaca jelas.
 *
 * Kolom wajib: nis, nama_depan, email, jenis_kelamin, tanggal_lahir.
 * Tanggal lahir wajib karena dipakai sebagai password awal siswa (ddmmyyyy),
 * sama seperti guru.
 *
 * Kolom `kelas` OPSIONAL: diisi kode kelas (lihat menu Kelas) bila sekaligus
 * ingin menempatkan siswa pada tahun ajaran aktif. Dikosongkan pun tidak apa —
 * data siswanya tetap masuk, penempatan kelas bisa menyusul.
 */
class StudentsTemplateExport implements FromArray, WithHeadings
{
    public function headings(): array
    {
        return StudentsExport::HEADINGS;
    }

    public function array(): array
    {
        // jenis_kelamin: L / P (boleh juga "Laki-laki" / "Perempuan").
        // Tanggal: YYYY-MM-DD atau DD/MM/YYYY.
        return [
            [
                '2024001', 'Ahmad', 'Fauzi', 'ahmad.fauzi@sekolah.sch.id', 'L',
                '2012-07-14', '0123456789', '3273011407120001', '081234567890',
                'Bandung', 'Jl. Melati No. 3', 'SDN 1 Bandung', 'X-IPA-1',
            ],
            [
                '2024002', 'Dewi', 'Lestari', 'dewi.lestari@sekolah.sch.id', 'P',
                '2012-11-02', '', '', '081234567891',
                'Bogor', 'Jl. Anggrek No. 9', '', '',
            ],
        ];
    }
}
