<?php

namespace Database\Seeders;

use App\Infrastructure\Persistence\Eloquent\Auth\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class DemoTeacherSeeder extends Seeder
{
    protected array $teachers = [
        // Matematika
        ['first' => 'Dr. Bambang', 'last' => 'Sudrajat, M.Pd', 'gender' => 'male', 'subject' => 'MTK', 'cert' => true],
        ['first' => 'Siti', 'last' => 'Aminah, S.Pd', 'gender' => 'female', 'subject' => 'MTK', 'cert' => true],
        ['first' => 'Rudi', 'last' => 'Hartanto, S.Pd', 'gender' => 'male', 'subject' => 'MTKP', 'cert' => false],

        // Bahasa Indonesia
        ['first' => 'Dra. Endang', 'last' => 'Sulistyowati', 'gender' => 'female', 'subject' => 'BIND', 'cert' => true],
        ['first' => 'Ahmad', 'last' => 'Fauzi, S.Pd', 'gender' => 'male', 'subject' => 'BIND', 'cert' => true],

        // Bahasa Inggris
        ['first' => 'Sarah', 'last' => 'Johnson, S.Pd', 'gender' => 'female', 'subject' => 'BING', 'cert' => true],
        ['first' => 'Michael', 'last' => 'Tan, M.Pd', 'gender' => 'male', 'subject' => 'BING', 'cert' => true],

        // IPA
        ['first' => 'Prof. Ir. Hadi', 'last' => 'Kusuma, Ph.D', 'gender' => 'male', 'subject' => 'FIS', 'cert' => true],
        ['first' => 'Ratna', 'last' => 'Dewi, S.Si', 'gender' => 'female', 'subject' => 'KIM', 'cert' => true],
        ['first' => 'Dr. Agung', 'last' => 'Prasetyo, M.Si', 'gender' => 'male', 'subject' => 'BIO', 'cert' => true],

        // IPS
        ['first' => 'Drs. Suparman', 'last' => 'Wijaya', 'gender' => 'male', 'subject' => 'GEO', 'cert' => true],
        ['first' => 'Lestari', 'last' => 'Handayani, S.Pd', 'gender' => 'female', 'subject' => 'EKO', 'cert' => true],
        ['first' => 'Bambang', 'last' => 'Nurcahyo, S.Pd', 'gender' => 'male', 'subject' => 'SOS', 'cert' => false],
        ['first' => 'Dra. Murni', 'last' => 'Sari, M.Hum', 'gender' => 'female', 'subject' => 'SEJA', 'cert' => true],

        // Agama & PKN
        ['first' => 'H. Abdul', 'last' => 'Rahman, S.Ag', 'gender' => 'male', 'subject' => 'PAI', 'cert' => true],
        ['first' => 'Yohanes', 'last' => 'Setiawan, S.Pd', 'gender' => 'male', 'subject' => 'PPKN', 'cert' => true],

        // Olahraga & Seni
        ['first' => 'Agus', 'last' => 'Supriadi, S.Pd', 'gender' => 'male', 'subject' => 'PJOK', 'cert' => true],
        ['first' => 'Dewi', 'last' => 'Kartika, S.Sn', 'gender' => 'female', 'subject' => 'SENB', 'cert' => false],

        // Informatika & Prakarya
        ['first' => 'Andi', 'last' => 'Firmansyah, S.Kom', 'gender' => 'male', 'subject' => 'INFO', 'cert' => false],
        ['first' => 'Retno', 'last' => 'Wulandari, S.Pd', 'gender' => 'female', 'subject' => 'PRAK', 'cert' => false],
    ];

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $tenant = DB::table('tenants')->first();
        if (!$tenant) return;

        $subjects = DB::table('subjects')
            ->where('tenant_id', $tenant->id)
            ->pluck('id', 'code');

        $departments = DB::table('departments')
            ->where('tenant_id', $tenant->id)
            ->pluck('id', 'code');

        $positions = DB::table('positions')
            ->where('tenant_id', $tenant->id)
            ->pluck('id', 'code');

        $count = 0;
        foreach ($this->teachers as $index => $teacher) {
            $userId = Str::uuid()->toString();
            $teacherId = Str::uuid()->toString();

            $username = strtolower(
                str_replace([' ', '.', ','], '', $teacher['first']) .
                substr(str_replace([' ', '.', ','], '', $teacher['last']), 0, 3) .
                rand(10, 99)
            );

            // Create user
            DB::table('users')->insertOrIgnore([
                'id' => $userId,
                'tenant_id' => $tenant->id,
                'username' => $username,
                'email' => $username . '@teacher.sms.local',
                'password' => Hash::make('password'),
                'email_verified_at' => now(),
                'status' => 'active',
                'user_type' => 'teacher',
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            // Create profile
            DB::table('user_profiles')->insertOrIgnore([
                'user_id' => $userId,
                'first_name' => $teacher['first'],
                'last_name' => $teacher['last'],
                'phone' => '08' . rand(1000000000, 9999999999),
                'gender' => $teacher['gender'],
                'birth_date' => (1970 + rand(0, 20)) . '-' . str_pad(rand(1, 12), 2, '0', STR_PAD_LEFT) . '-' . str_pad(rand(1, 28), 2, '0', STR_PAD_LEFT),
                'birth_place' => ['Jakarta', 'Bandung', 'Surabaya', 'Yogyakarta', 'Semarang'][rand(0, 4)],
                'address' => 'Jl. Guru No. ' . rand(1, 100),
                'city' => 'Jakarta',
                'province' => 'DKI Jakarta',
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            // Create teacher
            $nip = '19' . rand(70, 90) . str_pad(rand(1, 12), 2, '0', STR_PAD_LEFT) . str_pad(rand(1, 28), 2, '0', STR_PAD_LEFT) .
                   ' ' . date('Y') . str_pad(rand(1, 12), 2, '0', STR_PAD_LEFT) . ' ' . rand(1, 2) . ' ' . str_pad(rand(1, 999), 3, '0', STR_PAD_LEFT);

            DB::table('teachers')->insertOrIgnore([
                'id' => $teacherId,
                'tenant_id' => $tenant->id,
                'user_id' => $userId,
                'nip' => $nip,
                'join_date' => (2010 + rand(0, 10)) . '-' . str_pad(rand(1, 12), 2, '0', STR_PAD_LEFT) . '-01',
                'employment_status' => rand(0, 3) === 0 ? 'contract' : 'permanent',
                'certification_status' => $teacher['cert'] ? 'certified' : 'not_certified',
                'education_level' => ['S1', 'S2', 'S3'][rand(0, 2)],
                'teaching_experience_years' => rand(1, 20),
                'status' => 'active',
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            // Assign subject
            if (isset($subjects[$teacher['subject']])) {
                DB::table('teacher_subjects')->insertOrIgnore([
                    'teacher_id' => $teacherId,
                    'subject_id' => $subjects[$teacher['subject']],
                    'is_primary' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            // Assign role
            $user = User::find($userId);
            if ($user) {
                // First 3 teachers become wali kelas
                $user->assignRole($index < 3 ? 'wali_kelas' : 'guru');
            }

            $count++;
        }

        $this->command->info("Created {$count} demo teachers.");
    }
}
