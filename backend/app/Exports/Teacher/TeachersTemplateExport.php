<?php

namespace App\Exports\Teacher;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;

/**
 * Template import guru — berisi contoh baris agar format terbaca jelas.
 *
 * Kolom wajib: nama_depan, no_hp, nip, jenis_kelamin, tanggal_lahir.
 * Kewajiban ini berlaku pada jalur import saja (struktur tabel tidak
 * berubah); nama_belakang, nuptk, tempat_lahir, nik, alamat, dan
 * tanggal_masuk boleh dikosongkan.
 *
 * `email` (identitas login) kini OPSIONAL — dikosongkan berarti dibuatkan
 * otomatis dari username + domain sekolah. `email_kontak` adalah alamat surat
 * sungguhan tujuan OTP & notifikasi kehadiran; kalau `email` diisi dan
 * `email_kontak` dikosongkan, alamat itu dipakai untuk keduanya.
 * Lihat docs/EMAIL-OTOMATIS-AKUN.md.
 */
class TeachersTemplateExport implements FromArray, WithHeadings
{
    public function headings(): array
    {
        return TeachersExport::HEADINGS;
    }

    public function array(): array
    {
        // jenis_kelamin: L / P (boleh juga "Laki-laki" / "Perempuan").
        // Tanggal: YYYY-MM-DD atau DD/MM/YYYY.
        // Tanggal lahir dipakai sebagai password awal guru (format ddmmyyyy).
        // Baris kedua sengaja mengosongkan email untuk menunjukkan bahwa
        // email login boleh dibiarkan dibuatkan otomatis.
        return [
            [
                'Budi', 'Santoso', 'budi.santoso@sekolah.sch.id', '', '081234567890',
                '198501012010011001', '1234567890123456', 'L', 'Bandung',
                '1985-01-01', '3273010101850001', 'Jl. Merdeka No. 1', '2010-01-01',
            ],
            [
                'Siti', 'Aminah', '', 'siti.aminah@gmail.com', '081234567891',
                '199003152015032002', '', 'P', 'Bogor',
                '1990-03-15', '', 'Jl. Kenanga No. 7', '2015-03-01',
            ],
        ];
    }
}
