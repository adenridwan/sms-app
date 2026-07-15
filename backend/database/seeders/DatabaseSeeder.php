<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call([
            // Core seeders (run first)
            PermissionSeeder::class,
            RoleSeeder::class,

            // Demo tenant & users
            TenantSeeder::class,
            UserSeeder::class,

            // Academic seeders
            AcademicSeeder::class,

            // Master data seeders
            MasterDataSeeder::class,

            // Demo data seeders (optional - for development)
            DemoStudentSeeder::class,
            DemoTeacherSeeder::class,
            DemoAttendanceSeeder::class,
            DemoFinanceSeeder::class,
        ]);
    }
}
