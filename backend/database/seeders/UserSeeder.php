<?php

namespace Database\Seeders;

use App\Infrastructure\Persistence\Eloquent\Auth\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Get first tenant
        $tenant = DB::table('tenants')->first();

        // Create Super Admin (no tenant - system level)
        $superAdmin = User::firstOrCreate(
            ['email' => 'superadmin@sms.local'],
            [
                'id' => Str::uuid()->toString(),
                'tenant_id' => null,
                'username' => 'superadmin',
                'password' => Hash::make('password'),
                'email_verified_at' => now(),
                'status' => 'active',
                'user_type' => 'super_admin',
            ]
        );
        $superAdmin->assignRole('super_admin');

        // Create profile for super admin
        DB::table('user_profiles')->insertOrIgnore([
            'user_id' => $superAdmin->id,
            'first_name' => 'Super',
            'last_name' => 'Admin',
            'phone' => '081234567890',
            'gender' => 'male',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        if ($tenant) {
            $this->createTenantUsers($tenant->id);
        }

        $this->command->info('Created demo users with profiles.');
    }

    /**
     * Create users for a tenant.
     */
    protected function createTenantUsers(string $tenantId): void
    {
        $users = [
            // Admin
            [
                'user' => [
                    'username' => 'admin',
                    'email' => 'admin@demo.sms.local',
                    'user_type' => 'admin',
                ],
                'profile' => [
                    'first_name' => 'Administrator',
                    'last_name' => 'Sekolah',
                    'phone' => '081234567891',
                    'gender' => 'male',
                ],
                'role' => 'admin',
            ],
            // Kepala Sekolah
            [
                'user' => [
                    'username' => 'kepsek',
                    'email' => 'kepsek@demo.sms.local',
                    'user_type' => 'staff',
                ],
                'profile' => [
                    'first_name' => 'Dr. Ahmad',
                    'last_name' => 'Hidayat, M.Pd',
                    'phone' => '081234567892',
                    'gender' => 'male',
                ],
                'role' => 'kepala_sekolah',
            ],
            // Wakil Kepala Sekolah
            [
                'user' => [
                    'username' => 'wakasek',
                    'email' => 'wakasek@demo.sms.local',
                    'user_type' => 'staff',
                ],
                'profile' => [
                    'first_name' => 'Siti',
                    'last_name' => 'Rahayu, S.Pd',
                    'phone' => '081234567893',
                    'gender' => 'female',
                ],
                'role' => 'wakil_kepala_sekolah',
            ],
            // Guru 1
            [
                'user' => [
                    'username' => 'guru1',
                    'email' => 'guru1@demo.sms.local',
                    'user_type' => 'teacher',
                ],
                'profile' => [
                    'first_name' => 'Budi',
                    'last_name' => 'Santoso, S.Pd',
                    'phone' => '081234567894',
                    'gender' => 'male',
                ],
                'role' => 'guru',
            ],
            // Guru 2 (Wali Kelas)
            [
                'user' => [
                    'username' => 'guru2',
                    'email' => 'guru2@demo.sms.local',
                    'user_type' => 'teacher',
                ],
                'profile' => [
                    'first_name' => 'Dewi',
                    'last_name' => 'Lestari, S.Pd',
                    'phone' => '081234567895',
                    'gender' => 'female',
                ],
                'role' => 'wali_kelas',
            ],
            // Tata Usaha
            [
                'user' => [
                    'username' => 'tu',
                    'email' => 'tu@demo.sms.local',
                    'user_type' => 'staff',
                ],
                'profile' => [
                    'first_name' => 'Hendra',
                    'last_name' => 'Wijaya',
                    'phone' => '081234567896',
                    'gender' => 'male',
                ],
                'role' => 'tata_usaha',
            ],
            // Bendahara
            [
                'user' => [
                    'username' => 'bendahara',
                    'email' => 'bendahara@demo.sms.local',
                    'user_type' => 'staff',
                ],
                'profile' => [
                    'first_name' => 'Sri',
                    'last_name' => 'Mulyani',
                    'phone' => '081234567897',
                    'gender' => 'female',
                ],
                'role' => 'bendahara',
            ],
            // Pustakawan
            [
                'user' => [
                    'username' => 'pustakawan',
                    'email' => 'pustakawan@demo.sms.local',
                    'user_type' => 'staff',
                ],
                'profile' => [
                    'first_name' => 'Agus',
                    'last_name' => 'Prabowo',
                    'phone' => '081234567898',
                    'gender' => 'male',
                ],
                'role' => 'pustakawan',
            ],
            // Siswa 1
            [
                'user' => [
                    'username' => 'siswa1',
                    'email' => 'siswa1@demo.sms.local',
                    'user_type' => 'student',
                ],
                'profile' => [
                    'first_name' => 'Andi',
                    'last_name' => 'Pratama',
                    'phone' => '081234567899',
                    'gender' => 'male',
                    'birth_date' => '2008-05-15',
                    'birth_place' => 'Jakarta',
                ],
                'role' => 'siswa',
            ],
            // Siswa 2
            [
                'user' => [
                    'username' => 'siswa2',
                    'email' => 'siswa2@demo.sms.local',
                    'user_type' => 'student',
                ],
                'profile' => [
                    'first_name' => 'Sari',
                    'last_name' => 'Indah',
                    'phone' => '081234567900',
                    'gender' => 'female',
                    'birth_date' => '2008-08-20',
                    'birth_place' => 'Bandung',
                ],
                'role' => 'siswa',
            ],
            // Orang Tua
            [
                'user' => [
                    'username' => 'ortu1',
                    'email' => 'ortu1@demo.sms.local',
                    'user_type' => 'parent',
                ],
                'profile' => [
                    'first_name' => 'Joko',
                    'last_name' => 'Pratama',
                    'phone' => '081234567901',
                    'gender' => 'male',
                ],
                'role' => 'orang_tua',
            ],
        ];

        foreach ($users as $userData) {
            $user = User::firstOrCreate(
                ['email' => $userData['user']['email']],
                array_merge([
                    'id' => Str::uuid()->toString(),
                    'tenant_id' => $tenantId,
                    'password' => Hash::make('password'),
                    'email_verified_at' => now(),
                    'status' => 'active',
                ], $userData['user'])
            );

            // Assign role with tenant
            $user->assignRole($userData['role']);

            // Create profile
            DB::table('user_profiles')->insertOrIgnore(array_merge([
                'user_id' => $user->id,
                'created_at' => now(),
                'updated_at' => now(),
            ], $userData['profile']));
        }
    }
}
