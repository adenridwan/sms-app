<?php

namespace App\Support;

/**
 * Sumber kebenaran daftar menu sidebar di sisi server.
 *
 * Dipakai oleh:
 *  - MenuVisibilityService (resolusi menu yang boleh tampil untuk user &
 *    penyusunan matriks untuk halaman "Pengaturan Menu").
 *  - MenuSettingController (validasi menu_key & role).
 *
 * CATATAN sinkronisasi: `resources/js/layouts/MainLayout.tsx` memegang
 * ikon/judul/href untuk render sidebar dan memakai `key` yang SAMA dengan di
 * sini. Kalau menambah/menghapus menu, ubah di dua tempat.
 *
 * Aturan permission efektif (Opsi A — config hanya bisa MENYEMBUNYIKAN):
 *  - Anak dengan permission null MEWARISI permission group induknya.
 *  - `super_admin_only` = hanya super admin.
 *  - `protected` = tidak pernah bisa disembunyikan lewat config (anti-lockout),
 *    mis. halaman "Pengaturan Menu" itu sendiri.
 */
class MenuRegistry
{
    /**
     * Definisi menu (group → anak). `permission` null pada anak = warisi group.
     *
     * @return array<int, array<string, mixed>>
     */
    public static function tree(): array
    {
        return [
            [
                'key' => 'dashboard', 'title' => 'Dashboard', 'permission' => 'dashboard.view',
                'children' => [],
            ],
            [
                'key' => 'academic', 'title' => 'Akademik', 'permission' => 'academic.view',
                'children' => [
                    ['key' => 'academic.years', 'title' => 'Tahun Ajaran', 'permission' => 'academic-years.view'],
                    ['key' => 'academic.curricula', 'title' => 'Kurikulum', 'permission' => 'curricula.view'],
                    ['key' => 'academic.subjects', 'title' => 'Mata Pelajaran', 'permission' => 'subjects.view'],
                    ['key' => 'academic.grade-levels', 'title' => 'Tingkat Kelas', 'permission' => 'grade-levels.view'],
                    ['key' => 'academic.majors', 'title' => 'Jurusan', 'permission' => 'majors.view'],
                    ['key' => 'academic.classrooms', 'title' => 'Kelas', 'permission' => 'classrooms.view'],
                    ['key' => 'academic.schedules', 'title' => 'Jadwal', 'permission' => 'schedules.view'],
                ],
            ],
            [
                'key' => 'students', 'title' => 'Siswa', 'permission' => 'students.view',
                'children' => [
                    ['key' => 'students.data', 'title' => 'Data Siswa', 'permission' => null],
                    ['key' => 'students.enrollment', 'title' => 'Pendaftaran', 'permission' => 'students.enroll'],
                    ['key' => 'students.achievements', 'title' => 'Prestasi', 'permission' => null],
                ],
            ],
            [
                'key' => 'staff', 'title' => 'Guru & Staff', 'permission' => 'teachers.view',
                'children' => [
                    ['key' => 'staff.teachers', 'title' => 'Data Guru', 'permission' => 'teachers.view'],
                    ['key' => 'staff.staff', 'title' => 'Data Staff', 'permission' => 'staff.view'],
                    ['key' => 'staff.leave', 'title' => 'Pengajuan Cuti', 'permission' => null],
                ],
            ],
            [
                'key' => 'attendance', 'title' => 'Absensi', 'permission' => 'attendance.view',
                'children' => [
                    // Hanya guru & pegawai yang benar-benar presensi (punya
                    // attendance.check-in) — bukan semua yang bisa lihat
                    // grup Absensi (mis. bendahara/pustakawan/kepala sekolah).
                    ['key' => 'attendance.me', 'title' => 'Absensi Saya', 'permission' => 'attendance.check-in'],
                    ['key' => 'attendance.students', 'title' => 'Absensi Siswa', 'permission' => null],
                    ['key' => 'attendance.teachers', 'title' => 'Absensi Pegawai', 'permission' => null],
                    ['key' => 'attendance.reports', 'title' => 'Rekap Absensi', 'permission' => null],
                ],
            ],
            [
                'key' => 'grades', 'title' => 'Nilai & Ujian', 'permission' => 'grades.view',
                'children' => [
                    ['key' => 'grades.exams', 'title' => 'Ujian', 'permission' => 'exams.view'],
                    ['key' => 'grades.input', 'title' => 'Input Nilai', 'permission' => 'grades.input'],
                    ['key' => 'grades.recap', 'title' => 'Rekap Nilai', 'permission' => null],
                ],
            ],
            [
                'key' => 'finance', 'title' => 'Keuangan', 'permission' => 'finance.view',
                'children' => [
                    ['key' => 'finance.fees', 'title' => 'Tagihan', 'permission' => null],
                    ['key' => 'finance.payments', 'title' => 'Pembayaran', 'permission' => null],
                    ['key' => 'finance.reports', 'title' => 'Laporan', 'permission' => 'finance.report'],
                ],
            ],
            [
                'key' => 'library', 'title' => 'Perpustakaan', 'permission' => 'library.view',
                'children' => [
                    ['key' => 'library.books', 'title' => 'Katalog Buku', 'permission' => null],
                    ['key' => 'library.loans', 'title' => 'Peminjaman', 'permission' => null],
                    ['key' => 'library.members', 'title' => 'Anggota', 'permission' => null],
                ],
            ],
            [
                'key' => 'reports', 'title' => 'Laporan', 'permission' => 'reports.view',
                'children' => [
                    ['key' => 'reports.expense', 'title' => 'Pengeluaran', 'permission' => 'finance.report'],
                    ['key' => 'reports.report-cards', 'title' => 'Rapor', 'permission' => null],
                    ['key' => 'reports.generate', 'title' => 'Generate Laporan', 'permission' => null],
                ],
            ],
            [
                // Group tanpa permission — tampil bila ada anak yang lolos.
                'key' => 'settings', 'title' => 'Pengaturan', 'permission' => null,
                'children' => [
                    ['key' => 'settings.general', 'title' => 'Umum', 'permission' => 'settings.view'],
                    ['key' => 'settings.attendance', 'title' => 'Pengaturan Absensi', 'permission' => 'settings.attendance'],
                    ['key' => 'settings.menu', 'title' => 'Pengaturan Menu', 'permission' => 'settings.manage', 'protected' => true],
                    ['key' => 'settings.users', 'title' => 'Pengguna', 'permission' => null, 'super_admin_only' => true],
                    ['key' => 'settings.login-security', 'title' => 'Keamanan Login', 'permission' => 'settings.manage'],
                    ['key' => 'settings.devices', 'title' => 'Monitor Device', 'permission' => 'settings.manage'],
                    ['key' => 'settings.backups', 'title' => 'Backup Database', 'permission' => null, 'super_admin_only' => true],
                ],
            ],
        ];
    }

    /**
     * Semua entri (group + anak) dalam bentuk datar, dengan permission efektif.
     *
     * @return array<string, array{key:string,title:string,permission:?string,parent:?string,super_admin_only:bool,protected:bool}>
     */
    public static function flatten(): array
    {
        $flat = [];

        foreach (self::tree() as $group) {
            $flat[$group['key']] = [
                'key' => $group['key'],
                'title' => $group['title'],
                'permission' => $group['permission'] ?? null,
                'parent' => null,
                'super_admin_only' => (bool) ($group['super_admin_only'] ?? false),
                'protected' => (bool) ($group['protected'] ?? false),
            ];

            foreach ($group['children'] ?? [] as $child) {
                // Anak tanpa permission mewarisi permission group induk.
                $effective = $child['permission'] ?? ($group['permission'] ?? null);

                $flat[$child['key']] = [
                    'key' => $child['key'],
                    'title' => $child['title'],
                    'permission' => $effective,
                    'parent' => $group['key'],
                    'super_admin_only' => (bool) ($child['super_admin_only'] ?? false),
                    'protected' => (bool) ($child['protected'] ?? false),
                ];
            }
        }

        return $flat;
    }

    /**
     * Semua key valid (untuk validasi input controller).
     *
     * @return array<int, string>
     */
    public static function keys(): array
    {
        return array_keys(self::flatten());
    }

    public static function has(string $key): bool
    {
        return array_key_exists($key, self::flatten());
    }
}
