<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;

class PermissionSeeder extends Seeder
{
    /**
     * Permission groups and their permissions.
     */
    protected array $permissions = [
        // Dashboard
        'dashboard' => [
            'dashboard.view' => 'Lihat Dashboard',
            'dashboard.analytics' => 'Lihat Analytics',
        ],

        // User Management
        'users' => [
            'users.view' => 'Lihat Pengguna',
            'users.create' => 'Tambah Pengguna',
            'users.update' => 'Edit Pengguna',
            'users.delete' => 'Hapus Pengguna',
            'users.manage' => 'Kelola Pengguna',
            'users.reset-password' => 'Reset Password Pengguna',
        ],

        // Roles & Permissions
        'roles' => [
            'roles.view' => 'Lihat Role',
            'roles.create' => 'Tambah Role',
            'roles.update' => 'Edit Role',
            'roles.delete' => 'Hapus Role',
            'roles.assign' => 'Assign Role ke Pengguna',
        ],

        // Academic
        'academic' => [
            'academic.view' => 'Lihat Data Akademik',
            'academic.manage' => 'Kelola Data Akademik',
            'academic-years.view' => 'Lihat Tahun Ajaran',
            'academic-years.manage' => 'Kelola Tahun Ajaran',
            'curricula.view' => 'Lihat Kurikulum',
            'curricula.manage' => 'Kelola Kurikulum',
            'subjects.view' => 'Lihat Mata Pelajaran',
            'subjects.manage' => 'Kelola Mata Pelajaran',
            'classrooms.view' => 'Lihat Kelas',
            'classrooms.manage' => 'Kelola Kelas',
            'majors.view' => 'Lihat Jurusan',
            'majors.manage' => 'Kelola Jurusan',
            'grade-levels.view' => 'Lihat Tingkat Kelas',
            'grade-levels.manage' => 'Kelola Tingkat Kelas',
            'schedules.view' => 'Lihat Jadwal',
            'schedules.manage' => 'Kelola Jadwal',
        ],

        // Students
        'students' => [
            'students.view' => 'Lihat Data Siswa',
            'students.view-own' => 'Lihat Data Siswa Sendiri',
            'students.create' => 'Tambah Siswa',
            'students.update' => 'Edit Siswa',
            'students.delete' => 'Hapus Siswa',
            'students.manage' => 'Kelola Siswa',
            'students.enroll' => 'Pendaftaran Siswa',
            'students.export' => 'Export Data Siswa',
            'students.import' => 'Import Data Siswa',
        ],

        // Teachers
        'teachers' => [
            'teachers.view' => 'Lihat Data Guru',
            'teachers.view-own' => 'Lihat Data Guru Sendiri',
            'teachers.create' => 'Tambah Guru',
            'teachers.update' => 'Edit Guru',
            'teachers.delete' => 'Hapus Guru',
            'teachers.manage' => 'Kelola Guru',
            'teachers.assign-subjects' => 'Assign Mata Pelajaran',
        ],

        // Staff
        'staff' => [
            'staff.view' => 'Lihat Data Staff',
            'staff.create' => 'Tambah Staff',
            'staff.update' => 'Edit Staff',
            'staff.delete' => 'Hapus Staff',
            'staff.manage' => 'Kelola Staff',
        ],

        // Attendance
        'attendance' => [
            'attendance.view' => 'Lihat Absensi',
            'attendance.view-own' => 'Lihat Absensi Sendiri',
            'attendance.record' => 'Catat Absensi',
            'attendance.manage' => 'Kelola Absensi',
            'attendance.report' => 'Lihat Laporan Absensi',
            'attendance.check-in' => 'Check In',
            'attendance.check-out' => 'Check Out',
        ],

        // Exams & Grades
        'exams' => [
            'exams.view' => 'Lihat Ujian',
            'exams.create' => 'Buat Ujian',
            'exams.update' => 'Edit Ujian',
            'exams.delete' => 'Hapus Ujian',
            'exams.manage' => 'Kelola Ujian',
        ],

        'grades' => [
            'grades.view' => 'Lihat Nilai',
            'grades.view-own' => 'Lihat Nilai Sendiri',
            'grades.input' => 'Input Nilai',
            'grades.update' => 'Edit Nilai',
            'grades.manage' => 'Kelola Nilai',
            'grades.finalize' => 'Finalisasi Nilai',
            'grades.export' => 'Export Nilai',
        ],

        // Finance
        'finance' => [
            'finance.view' => 'Lihat Keuangan',
            'finance.view-own' => 'Lihat Keuangan Sendiri',
            'finance.manage' => 'Kelola Keuangan',
            'fees.view' => 'Lihat Biaya',
            'fees.manage' => 'Kelola Biaya',
            'fees.generate' => 'Generate Tagihan',
            'payments.view' => 'Lihat Pembayaran',
            'payments.create' => 'Terima Pembayaran',
            'payments.verify' => 'Verifikasi Pembayaran',
            'payments.manage' => 'Kelola Pembayaran',
            'discounts.view' => 'Lihat Diskon',
            'discounts.manage' => 'Kelola Diskon',
            'finance.report' => 'Lihat Laporan Keuangan',
        ],

        // Library
        'library' => [
            'library.view' => 'Lihat Perpustakaan',
            'library.manage' => 'Kelola Perpustakaan',
            'books.view' => 'Lihat Buku',
            'books.create' => 'Tambah Buku',
            'books.update' => 'Edit Buku',
            'books.delete' => 'Hapus Buku',
            'loans.view' => 'Lihat Peminjaman',
            'loans.create' => 'Buat Peminjaman',
            'loans.return' => 'Proses Pengembalian',
            'loans.manage' => 'Kelola Peminjaman',
        ],

        // Reports
        'reports' => [
            'reports.view' => 'Lihat Laporan',
            'reports.generate' => 'Generate Laporan',
            'reports.export' => 'Export Laporan',
            'report-cards.view' => 'Lihat Rapor',
            'report-cards.generate' => 'Generate Rapor',
            'report-cards.approve' => 'Approve Rapor',
            'report-cards.print' => 'Cetak Rapor',
        ],

        // Notifications
        'notifications' => [
            'notifications.view' => 'Lihat Notifikasi',
            'notifications.manage' => 'Kelola Notifikasi',
            'announcements.view' => 'Lihat Pengumuman',
            'announcements.create' => 'Buat Pengumuman',
            'announcements.update' => 'Edit Pengumuman',
            'announcements.delete' => 'Hapus Pengumuman',
            'announcements.publish' => 'Publish Pengumuman',
        ],

        // Settings
        'settings' => [
            'settings.view' => 'Lihat Pengaturan',
            'settings.manage' => 'Kelola Pengaturan',
            'settings.school' => 'Pengaturan Sekolah',
            'settings.academic' => 'Pengaturan Akademik',
            'settings.finance' => 'Pengaturan Keuangan',
            'settings.attendance' => 'Pengaturan Absensi',
        ],

        // Audit & Logs
        'audit' => [
            'audit.view' => 'Lihat Audit Log',
            'audit.export' => 'Export Audit Log',
            'activity.view' => 'Lihat Activity Log',
        ],

        // System
        'system' => [
            'system.health' => 'Lihat System Health',
            'system.metrics' => 'Lihat System Metrics',
            'system.backup' => 'Backup System',
            'system.maintenance' => 'Mode Maintenance',
        ],

        // Tenant
        'tenants' => [
            'tenants.view' => 'Lihat Tenant',
            'tenants.create' => 'Buat Tenant',
            'tenants.update' => 'Edit Tenant',
            'tenants.delete' => 'Hapus Tenant',
            'tenants.manage' => 'Kelola Tenant',
        ],
    ];

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Clear cache
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        foreach ($this->permissions as $group => $permissions) {
            foreach ($permissions as $name => $description) {
                Permission::firstOrCreate(
                    ['name' => $name, 'guard_name' => 'web'],
                    ['group' => $group, 'description' => $description]
                );
            }
        }

        $this->command->info('Created ' . Permission::count() . ' permissions.');
    }
}
