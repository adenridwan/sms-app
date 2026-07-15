<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class AcademicSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $tenant = DB::table('tenants')->first();

        if (!$tenant) {
            $this->command->warn('No tenant found. Skipping academic seeder.');
            return;
        }

        $this->seedAcademicYears($tenant->id);
        $this->seedCurricula($tenant->id);
        $this->seedGradeLevels($tenant->id);
        $this->seedMajors($tenant->id);
        $this->seedTimeSlots($tenant->id);
        $this->seedSubjects($tenant->id);

        $this->command->info('Created academic master data.');
    }

    protected function seedAcademicYears(string $tenantId): void
    {
        $currentYear = (int) date('Y');
        $startMonth = 7; // July

        // Current academic year
        $academicYearId = Str::uuid()->toString();
        DB::table('academic_years')->insertOrIgnore([
            'id' => $academicYearId,
            'tenant_id' => $tenantId,
            'name' => $currentYear . '/' . ($currentYear + 1),
            'start_date' => "$currentYear-07-01",
            'end_date' => ($currentYear + 1) . "-06-30",
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Semesters
        DB::table('semesters')->insertOrIgnore([
            [
                'id' => Str::uuid()->toString(),
                'tenant_id' => $tenantId,
                'academic_year_id' => $academicYearId,
                'name' => 'Semester 1 (Ganjil)',
                'number' => 1,
                'start_date' => "$currentYear-07-01",
                'end_date' => "$currentYear-12-31",
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'id' => Str::uuid()->toString(),
                'tenant_id' => $tenantId,
                'academic_year_id' => $academicYearId,
                'name' => 'Semester 2 (Genap)',
                'number' => 2,
                'start_date' => ($currentYear + 1) . "-01-01",
                'end_date' => ($currentYear + 1) . "-06-30",
                'is_active' => false,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);
    }

    protected function seedCurricula(string $tenantId): void
    {
        $curricula = [
            ['name' => 'Kurikulum Merdeka', 'code' => 'KM', 'is_active' => true],
            ['name' => 'Kurikulum 2013 Revisi', 'code' => 'K13R', 'is_active' => false],
        ];

        foreach ($curricula as $curriculum) {
            DB::table('curricula')->insertOrIgnore([
                'id' => Str::uuid()->toString(),
                'tenant_id' => $tenantId,
                'name' => $curriculum['name'],
                'code' => $curriculum['code'],
                'is_active' => $curriculum['is_active'],
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    protected function seedGradeLevels(string $tenantId): void
    {
        $levels = [
            ['name' => 'Kelas 10', 'code' => 'X', 'order' => 1],
            ['name' => 'Kelas 11', 'code' => 'XI', 'order' => 2],
            ['name' => 'Kelas 12', 'code' => 'XII', 'order' => 3],
        ];

        foreach ($levels as $level) {
            DB::table('grade_levels')->insertOrIgnore([
                'id' => Str::uuid()->toString(),
                'tenant_id' => $tenantId,
                'name' => $level['name'],
                'code' => $level['code'],
                'order' => $level['order'],
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    protected function seedMajors(string $tenantId): void
    {
        $majors = [
            ['name' => 'Ilmu Pengetahuan Alam', 'code' => 'IPA'],
            ['name' => 'Ilmu Pengetahuan Sosial', 'code' => 'IPS'],
            ['name' => 'Bahasa dan Budaya', 'code' => 'BAHASA'],
        ];

        foreach ($majors as $major) {
            DB::table('majors')->insertOrIgnore([
                'id' => Str::uuid()->toString(),
                'tenant_id' => $tenantId,
                'name' => $major['name'],
                'code' => $major['code'],
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    protected function seedTimeSlots(string $tenantId): void
    {
        $slots = [
            ['name' => 'Jam 1', 'start' => '07:00', 'end' => '07:45', 'order' => 1, 'break' => false],
            ['name' => 'Jam 2', 'start' => '07:45', 'end' => '08:30', 'order' => 2, 'break' => false],
            ['name' => 'Jam 3', 'start' => '08:30', 'end' => '09:15', 'order' => 3, 'break' => false],
            ['name' => 'Istirahat 1', 'start' => '09:15', 'end' => '09:30', 'order' => 4, 'break' => true],
            ['name' => 'Jam 4', 'start' => '09:30', 'end' => '10:15', 'order' => 5, 'break' => false],
            ['name' => 'Jam 5', 'start' => '10:15', 'end' => '11:00', 'order' => 6, 'break' => false],
            ['name' => 'Jam 6', 'start' => '11:00', 'end' => '11:45', 'order' => 7, 'break' => false],
            ['name' => 'Istirahat 2', 'start' => '11:45', 'end' => '12:30', 'order' => 8, 'break' => true],
            ['name' => 'Jam 7', 'start' => '12:30', 'end' => '13:15', 'order' => 9, 'break' => false],
            ['name' => 'Jam 8', 'start' => '13:15', 'end' => '14:00', 'order' => 10, 'break' => false],
            ['name' => 'Jam 9', 'start' => '14:00', 'end' => '14:45', 'order' => 11, 'break' => false],
            ['name' => 'Jam 10', 'start' => '14:45', 'end' => '15:30', 'order' => 12, 'break' => false],
        ];

        foreach ($slots as $slot) {
            DB::table('time_slots')->insertOrIgnore([
                'id' => Str::uuid()->toString(),
                'tenant_id' => $tenantId,
                'name' => $slot['name'],
                'start_time' => $slot['start'],
                'end_time' => $slot['end'],
                'order' => $slot['order'],
                'is_break' => $slot['break'],
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    protected function seedSubjects(string $tenantId): void
    {
        $curriculum = DB::table('curricula')
            ->where('tenant_id', $tenantId)
            ->where('is_active', true)
            ->first();

        $subjects = [
            // Wajib
            ['name' => 'Pendidikan Agama', 'code' => 'PAI', 'category' => 'Wajib'],
            ['name' => 'Pendidikan Pancasila', 'code' => 'PPKN', 'category' => 'Wajib'],
            ['name' => 'Bahasa Indonesia', 'code' => 'BIND', 'category' => 'Wajib'],
            ['name' => 'Bahasa Inggris', 'code' => 'BING', 'category' => 'Wajib'],
            ['name' => 'Matematika', 'code' => 'MTK', 'category' => 'Wajib'],
            ['name' => 'Sejarah Indonesia', 'code' => 'SEJA', 'category' => 'Wajib'],
            ['name' => 'Pendidikan Jasmani', 'code' => 'PJOK', 'category' => 'Wajib'],
            ['name' => 'Seni Budaya', 'code' => 'SENB', 'category' => 'Wajib'],
            ['name' => 'Informatika', 'code' => 'INFO', 'category' => 'Wajib'],

            // Peminatan IPA
            ['name' => 'Matematika Peminatan', 'code' => 'MTKP', 'category' => 'Peminatan IPA'],
            ['name' => 'Fisika', 'code' => 'FIS', 'category' => 'Peminatan IPA'],
            ['name' => 'Kimia', 'code' => 'KIM', 'category' => 'Peminatan IPA'],
            ['name' => 'Biologi', 'code' => 'BIO', 'category' => 'Peminatan IPA'],

            // Peminatan IPS
            ['name' => 'Geografi', 'code' => 'GEO', 'category' => 'Peminatan IPS'],
            ['name' => 'Sejarah Peminatan', 'code' => 'SEJP', 'category' => 'Peminatan IPS'],
            ['name' => 'Sosiologi', 'code' => 'SOS', 'category' => 'Peminatan IPS'],
            ['name' => 'Ekonomi', 'code' => 'EKO', 'category' => 'Peminatan IPS'],

            // Muatan Lokal
            ['name' => 'Bahasa Daerah', 'code' => 'BADA', 'category' => 'Muatan Lokal'],
            ['name' => 'Prakarya', 'code' => 'PRAK', 'category' => 'Muatan Lokal'],
        ];

        foreach ($subjects as $subject) {
            DB::table('subjects')->insertOrIgnore([
                'id' => Str::uuid()->toString(),
                'tenant_id' => $tenantId,
                'curriculum_id' => $curriculum?->id,
                'name' => $subject['name'],
                'code' => $subject['code'],
                'category' => $subject['category'],
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }
}
