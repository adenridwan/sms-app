<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class MasterDataSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $tenant = DB::table('tenants')->first();

        if (!$tenant) {
            $this->command->warn('No tenant found. Skipping master data seeder.');
            return;
        }

        $this->seedExamTypes($tenant->id);
        $this->seedFeeTypes($tenant->id);
        $this->seedPaymentMethods($tenant->id);
        $this->seedBookCategories($tenant->id);
        $this->seedDepartments($tenant->id);
        $this->seedPositions($tenant->id);
        $this->seedAttendanceSettings($tenant->id);
        $this->seedLibrarySettings($tenant->id);

        $this->command->info('Created master data.');
    }

    protected function seedExamTypes(string $tenantId): void
    {
        $types = [
            ['name' => 'Ulangan Harian', 'code' => 'UH', 'weight' => 1.00],
            ['name' => 'Tugas', 'code' => 'TGS', 'weight' => 0.50],
            ['name' => 'Quiz', 'code' => 'QZ', 'weight' => 0.50],
            ['name' => 'Ulangan Tengah Semester', 'code' => 'UTS', 'weight' => 2.00],
            ['name' => 'Ulangan Akhir Semester', 'code' => 'UAS', 'weight' => 3.00],
            ['name' => 'Praktikum', 'code' => 'PRAK', 'weight' => 1.50],
            ['name' => 'Ujian Praktik', 'code' => 'UP', 'weight' => 2.00],
        ];

        foreach ($types as $type) {
            DB::table('exam_types')->insertOrIgnore([
                'id' => Str::uuid()->toString(),
                'tenant_id' => $tenantId,
                'name' => $type['name'],
                'code' => $type['code'],
                'default_weight' => $type['weight'],
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    protected function seedFeeTypes(string $tenantId): void
    {
        $types = [
            ['name' => 'SPP', 'code' => 'SPP', 'frequency' => 'monthly', 'mandatory' => true],
            ['name' => 'Uang Pangkal', 'code' => 'UP', 'frequency' => 'once', 'mandatory' => true],
            ['name' => 'Uang Gedung', 'code' => 'UG', 'frequency' => 'yearly', 'mandatory' => true],
            ['name' => 'Uang Kegiatan', 'code' => 'UK', 'frequency' => 'semester', 'mandatory' => true],
            ['name' => 'Uang Seragam', 'code' => 'US', 'frequency' => 'once', 'mandatory' => false],
            ['name' => 'Uang Buku', 'code' => 'UB', 'frequency' => 'yearly', 'mandatory' => false],
            ['name' => 'Uang Praktikum', 'code' => 'UPRAK', 'frequency' => 'semester', 'mandatory' => false],
            ['name' => 'Uang Ekstrakulikuler', 'code' => 'UEKS', 'frequency' => 'monthly', 'mandatory' => false],
            ['name' => 'Uang Wisuda', 'code' => 'UW', 'frequency' => 'once', 'mandatory' => false],
        ];

        foreach ($types as $type) {
            DB::table('fee_types')->insertOrIgnore([
                'id' => Str::uuid()->toString(),
                'tenant_id' => $tenantId,
                'name' => $type['name'],
                'code' => $type['code'],
                'frequency' => $type['frequency'],
                'is_mandatory' => $type['mandatory'],
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    protected function seedPaymentMethods(string $tenantId): void
    {
        $methods = [
            ['name' => 'Tunai', 'code' => 'CASH', 'type' => 'cash', 'fee' => 0],
            ['name' => 'Transfer Bank BCA', 'code' => 'BCA', 'type' => 'bank_transfer', 'fee' => 0],
            ['name' => 'Transfer Bank Mandiri', 'code' => 'MANDIRI', 'type' => 'bank_transfer', 'fee' => 0],
            ['name' => 'Transfer Bank BNI', 'code' => 'BNI', 'type' => 'bank_transfer', 'fee' => 0],
            ['name' => 'Transfer Bank BRI', 'code' => 'BRI', 'type' => 'bank_transfer', 'fee' => 0],
            ['name' => 'Virtual Account', 'code' => 'VA', 'type' => 'virtual_account', 'fee' => 2500],
            ['name' => 'QRIS', 'code' => 'QRIS', 'type' => 'e_wallet', 'fee' => 0],
            ['name' => 'GoPay', 'code' => 'GOPAY', 'type' => 'e_wallet', 'fee' => 0],
            ['name' => 'OVO', 'code' => 'OVO', 'type' => 'e_wallet', 'fee' => 0],
            ['name' => 'Dana', 'code' => 'DANA', 'type' => 'e_wallet', 'fee' => 0],
        ];

        foreach ($methods as $method) {
            DB::table('payment_methods')->insertOrIgnore([
                'id' => Str::uuid()->toString(),
                'tenant_id' => $tenantId,
                'name' => $method['name'],
                'code' => $method['code'],
                'type' => $method['type'],
                'admin_fee' => $method['fee'],
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    protected function seedBookCategories(string $tenantId): void
    {
        $categories = [
            ['name' => 'Fiksi', 'code' => 'FIK'],
            ['name' => 'Non-Fiksi', 'code' => 'NON'],
            ['name' => 'Sains', 'code' => 'SCI'],
            ['name' => 'Teknologi', 'code' => 'TEC'],
            ['name' => 'Sejarah', 'code' => 'HIS'],
            ['name' => 'Sastra', 'code' => 'LIT'],
            ['name' => 'Matematika', 'code' => 'MAT'],
            ['name' => 'Bahasa', 'code' => 'LNG'],
            ['name' => 'Agama', 'code' => 'REL'],
            ['name' => 'Buku Pelajaran', 'code' => 'TXT'],
            ['name' => 'Referensi', 'code' => 'REF'],
            ['name' => 'Majalah', 'code' => 'MAG'],
            ['name' => 'Ensiklopedia', 'code' => 'ENC'],
        ];

        foreach ($categories as $category) {
            DB::table('book_categories')->insertOrIgnore([
                'id' => Str::uuid()->toString(),
                'tenant_id' => $tenantId,
                'name' => $category['name'],
                'code' => $category['code'],
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    protected function seedDepartments(string $tenantId): void
    {
        $departments = [
            ['name' => 'Kurikulum', 'code' => 'KUR'],
            ['name' => 'Kesiswaan', 'code' => 'KES'],
            ['name' => 'Sarana Prasarana', 'code' => 'SARPRAS'],
            ['name' => 'Hubungan Masyarakat', 'code' => 'HUMAS'],
            ['name' => 'Tata Usaha', 'code' => 'TU'],
            ['name' => 'Perpustakaan', 'code' => 'LIB'],
            ['name' => 'Laboratorium', 'code' => 'LAB'],
            ['name' => 'Bimbingan Konseling', 'code' => 'BK'],
        ];

        foreach ($departments as $dept) {
            DB::table('departments')->insertOrIgnore([
                'id' => Str::uuid()->toString(),
                'tenant_id' => $tenantId,
                'name' => $dept['name'],
                'code' => $dept['code'],
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    protected function seedPositions(string $tenantId): void
    {
        $positions = [
            ['name' => 'Kepala Sekolah', 'code' => 'KEPSEK', 'level' => 1],
            ['name' => 'Wakil Kepala Sekolah', 'code' => 'WAKASEK', 'level' => 2],
            ['name' => 'Kepala Bidang', 'code' => 'KABID', 'level' => 3],
            ['name' => 'Koordinator', 'code' => 'KOOR', 'level' => 4],
            ['name' => 'Guru Senior', 'code' => 'GURU_SR', 'level' => 5],
            ['name' => 'Guru', 'code' => 'GURU', 'level' => 6],
            ['name' => 'Guru Junior', 'code' => 'GURU_JR', 'level' => 7],
            ['name' => 'Staff Senior', 'code' => 'STAFF_SR', 'level' => 8],
            ['name' => 'Staff', 'code' => 'STAFF', 'level' => 9],
            ['name' => 'Staff Junior', 'code' => 'STAFF_JR', 'level' => 10],
        ];

        foreach ($positions as $pos) {
            DB::table('positions')->insertOrIgnore([
                'id' => Str::uuid()->toString(),
                'tenant_id' => $tenantId,
                'name' => $pos['name'],
                'code' => $pos['code'],
                'level' => $pos['level'],
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    protected function seedAttendanceSettings(string $tenantId): void
    {
        DB::table('attendance_settings')->insertOrIgnore([
            'id' => Str::uuid()->toString(),
            'tenant_id' => $tenantId,
            'check_in_start' => '06:00:00',
            'check_in_end' => '07:30:00',
            'check_out_start' => '14:00:00',
            'check_out_end' => '17:00:00',
            'late_tolerance_minutes' => 15,
            'require_location' => false,
            'require_photo' => false,
            'working_days' => json_encode([1, 2, 3, 4, 5]), // Mon-Fri
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    protected function seedLibrarySettings(string $tenantId): void
    {
        DB::table('library_settings')->insertOrIgnore([
            'id' => Str::uuid()->toString(),
            'tenant_id' => $tenantId,
            'default_loan_days' => 7,
            'max_loan_days' => 14,
            'max_extensions' => 2,
            'extension_days' => 7,
            'daily_fine' => 500.00,
            'max_fine' => 50000.00,
            'lost_book_multiplier' => 2.00,
            'allow_reservations' => true,
            'reservation_expiry_days' => 3,
            'operating_hours' => json_encode([
                'monday' => ['08:00', '16:00'],
                'tuesday' => ['08:00', '16:00'],
                'wednesday' => ['08:00', '16:00'],
                'thursday' => ['08:00', '16:00'],
                'friday' => ['08:00', '15:00'],
                'saturday' => ['08:00', '12:00'],
                'sunday' => null,
            ]),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
