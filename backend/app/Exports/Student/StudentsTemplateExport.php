<?php

namespace App\Exports\Student;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;

/**
 * Template import siswa — berisi contoh baris agar formatnya terbaca jelas.
 *
 * Kolom wajib: nis, nama_depan, jenis_kelamin, tanggal_lahir.
 * Tanggal lahir wajib karena dipakai sebagai password awal siswa (ddmmyyyy),
 * sama seperti guru.
 *
 * TIDAK ada kolom email login di sini — email login selalu dibuat otomatis
 * dari nama depan + NIS + domain sekolah (docs/EMAIL-OTOMATIS-AKUN.md).
 * Karena itu heading template sengaja berbeda dari StudentsExport::HEADINGS
 * yang tetap memuat `email` sebagai informasi untuk dibagikan ke siswa.
 *
 * Kolom `email_kontak` OPSIONAL: alamat surat sungguhan (mis. email orang tua)
 * tujuan OTP dan notifikasi. Boleh sama untuk beberapa anak — memang tidak
 * dibuat unik supaya satu orang tua bisa memakainya untuk semua anaknya.
 *
 * Kolom `kelas` OPSIONAL dan berisi **kelas/rombel** (mis. "A 1"), BUKAN
 * tingkat kelas. Isinya dicocokkan ke nama maupun kode kelas pada tahun ajaran
 * aktif — tulis saja nama yang terlihat di menu Kelas. Dikosongkan pun tidak
 * apa: data siswanya tetap masuk, penempatan kelas bisa menyusul.
 *
 * Contoh di bawah sengaja memakai nama, bukan kode: kode kelas dibuat otomatis
 * (`KLS01`, `KLS02`) dan tidak ditampilkan di menu Kelas, jadi contoh berupa
 * kode justru membuat admin menebak-nebak.
 */
class StudentsTemplateExport implements FromArray, WithHeadings
{
    public const HEADINGS = [
        'nis',
        'nama_depan',
        'nama_belakang',
        'email_kontak',
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

    public function headings(): array
    {
        return self::HEADINGS;
    }

    public function array(): array
    {
        // jenis_kelamin: L / P (boleh juga "Laki-laki" / "Perempuan").
        // Tanggal: YYYY-MM-DD atau DD/MM/YYYY.
        // Baris kedua sengaja mengosongkan email_kontak untuk menunjukkan
        // bahwa kolom itu boleh dibiarkan kosong.
        return [
            [
                '2024001', 'Ahmad', 'Fauzi', 'orangtua.ahmad@gmail.com', 'L',
                '2012-07-14', '0123456789', '3273011407120001', '081234567890',
                'Bandung', 'Jl. Melati No. 3', 'SDN 1 Bandung', 'A 1',
            ],
            [
                '2024002', 'Dewi', 'Lestari', '', 'P',
                '2012-11-02', '', '', '081234567891',
                'Bogor', 'Jl. Anggrek No. 9', '', '',
            ],
        ];
    }
}
