<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class TenantSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $tenants = [
            [
                'id' => Str::uuid()->toString(),
                'name' => 'SMA Negeri 1 Demo',
                'slug' => 'sman1-demo',
                'domain' => 'sman1.sms.local',
                'email' => 'admin@sman1.sms.local',
                'phone' => '021-1234567',
                'address' => 'Jl. Pendidikan No. 1, Jakarta',
                'npsn' => '20100001',
                'level' => 'sma',
                'status' => 'active',
                'settings' => json_encode([
                    'theme' => 'default',
                    'language' => 'id',
                    'timezone' => 'Asia/Jakarta',
                    'academic_year_format' => 'YYYY/YYYY',
                    'grading_system' => 'numeric', // numeric, letter
                    'attendance_mode' => 'daily', // daily, per_subject
                ]),
                'features' => json_encode([
                    'finance' => true,
                    'library' => true,
                    'attendance_gps' => false,
                    'parent_portal' => true,
                    'online_exam' => false,
                ]),
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'id' => Str::uuid()->toString(),
                'name' => 'SMK Teknologi Demo',
                'slug' => 'smk-tekno-demo',
                'domain' => 'smktekno.sms.local',
                'email' => 'admin@smktekno.sms.local',
                'phone' => '021-7654321',
                'address' => 'Jl. Industri No. 10, Bandung',
                'npsn' => '20100002',
                'level' => 'smk',
                'status' => 'active',
                'settings' => json_encode([
                    'theme' => 'default',
                    'language' => 'id',
                    'timezone' => 'Asia/Jakarta',
                    'academic_year_format' => 'YYYY/YYYY',
                    'grading_system' => 'numeric',
                    'attendance_mode' => 'daily',
                ]),
                'features' => json_encode([
                    'finance' => true,
                    'library' => true,
                    'attendance_gps' => true,
                    'parent_portal' => true,
                    'online_exam' => true,
                ]),
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ];

        foreach ($tenants as $tenant) {
            DB::table('tenants')->insertOrIgnore($tenant);
        }

        $this->command->info('Created ' . count($tenants) . ' demo tenants.');
    }
}
