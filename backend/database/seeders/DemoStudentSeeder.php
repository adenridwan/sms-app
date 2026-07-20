<?php

namespace Database\Seeders;

use App\Infrastructure\Persistence\Eloquent\Auth\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class DemoStudentSeeder extends Seeder
{
    /**
     * Indonesian first names.
     */
    protected array $firstNames = [
        'male' => ['Andi', 'Budi', 'Cahyo', 'Dimas', 'Eko', 'Fajar', 'Gilang', 'Hendra', 'Irfan', 'Joko',
                   'Kevin', 'Lukman', 'Muhammad', 'Naufal', 'Oscar', 'Putra', 'Qori', 'Rizki', 'Satria', 'Taufik',
                   'Umar', 'Vino', 'Wahyu', 'Yusuf', 'Zaki', 'Agus', 'Bayu', 'Candra', 'Dhani', 'Erik'],
        'female' => ['Ayu', 'Bunga', 'Citra', 'Dewi', 'Eka', 'Fitri', 'Gita', 'Hani', 'Indah', 'Jasmine',
                     'Kartika', 'Lina', 'Maya', 'Nadia', 'Oktavia', 'Putri', 'Qory', 'Rina', 'Sari', 'Tia',
                     'Umi', 'Vina', 'Wulan', 'Yuni', 'Zahra', 'Anisa', 'Bella', 'Clara', 'Dian', 'Erna'],
    ];

    protected array $lastNames = [
        'Pratama', 'Wijaya', 'Kusuma', 'Saputra', 'Hidayat', 'Ramadhan', 'Nugroho', 'Setiawan', 'Permana', 'Santoso',
        'Putra', 'Wibowo', 'Cahyadi', 'Firmansyah', 'Gunawan', 'Hartono', 'Ismail', 'Jaya', 'Kurniawan', 'Lesmana',
        'Maulana', 'Nanda', 'Oktavian', 'Prayoga', 'Rahman', 'Surya', 'Tanaka', 'Utama', 'Vega', 'Wardhana',
    ];

    /**
     * Sequential counter so generated NIS/NISN/usernames are unique.
     */
    protected int $sequence = 0;

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $this->sequence = (int) DB::table('students')->count();
        $tenant = DB::table('tenants')->first();
        if (!$tenant) return;

        $academicYear = DB::table('academic_years')
            ->where('tenant_id', $tenant->id)
            ->where('is_active', true)
            ->first();
        if (!$academicYear) return;

        $gradeLevels = DB::table('grade_levels')
            ->where('tenant_id', $tenant->id)
            ->orderBy('order')
            ->get();

        $majors = DB::table('majors')
            ->where('tenant_id', $tenant->id)
            ->get();

        // Create classrooms and students
        $studentCount = 0;
        foreach ($gradeLevels as $gradeLevel) {
            foreach ($majors as $major) {
                // Create 2 classes per grade/major
                for ($classNum = 1; $classNum <= 2; $classNum++) {
                    $classroomId = $this->createClassroom($tenant->id, $academicYear->id, $gradeLevel, $major, $classNum);

                    // Create 30 students per class
                    for ($i = 1; $i <= 30; $i++) {
                        $this->createStudent($tenant->id, $academicYear->id, $classroomId, $gradeLevel, $i);
                        $studentCount++;
                    }
                }
            }
        }

        $this->command->info("Created {$studentCount} demo students.");
    }

    protected function createClassroom(string $tenantId, string $academicYearId, object $gradeLevel, object $major, int $classNum): string
    {
        $id = Str::uuid()->toString();
        $name = "{$gradeLevel->code} {$major->code} {$classNum}";
        $code = "{$gradeLevel->code}-{$major->code}-{$classNum}";

        DB::table('classrooms')->insertOrIgnore([
            'id' => $id,
            'tenant_id' => $tenantId,
            'academic_year_id' => $academicYearId,
            'grade_level_id' => $gradeLevel->id,
            'major_id' => $major->id,
            'name' => $name,
            'code' => $code,
            'capacity' => 36,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $id;
    }

    protected function createStudent(string $tenantId, string $academicYearId, string $classroomId, object $gradeLevel, int $number): void
    {
        $gender = rand(0, 1) ? 'male' : 'female';
        $firstName = $this->firstNames[$gender][array_rand($this->firstNames[$gender])];
        $lastName = $this->lastNames[array_rand($this->lastNames)];

        $userId = Str::uuid()->toString();
        $studentId = Str::uuid()->toString();

        $seq = ++$this->sequence;
        $nis = date('Y') . str_pad($seq, 4, '0', STR_PAD_LEFT);
        $nisn = '00' . str_pad(10000000 + $seq, 8, '0', STR_PAD_LEFT);
        $username = strtolower($firstName . '.' . substr($lastName, 0, 3)) . $seq;
        $email = $username . '@student.sms.local';

        // Create user
        $inserted = DB::table('users')->insertOrIgnore([
            'id' => $userId,
            'tenant_id' => $tenantId,
            'username' => $username,
            'email' => $email,
            'password' => Hash::make('password'),
            'email_verified_at' => now(),
            'status' => 'active',
            'user_type' => 'student',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // User was skipped (already exists) — dependent rows would violate FKs
        if ($inserted === 0) {
            return;
        }

        // Create profile
        $birthYear = match($gradeLevel->code) {
            'X' => 2009,
            'XI' => 2008,
            'XII' => 2007,
            default => 2008,
        };

        DB::table('user_profiles')->insertOrIgnore([
            'user_id' => $userId,
            'first_name' => $firstName,
            'last_name' => $lastName,
            'phone' => '08' . rand(1000000000, 9999999999),
            'gender' => $gender,
            'birth_date' => $birthYear . '-' . str_pad(rand(1, 12), 2, '0', STR_PAD_LEFT) . '-' . str_pad(rand(1, 28), 2, '0', STR_PAD_LEFT),
            'birth_place' => ['Jakarta', 'Bandung', 'Surabaya', 'Yogyakarta', 'Semarang'][rand(0, 4)],
            'religion' => ['Islam', 'Kristen', 'Katolik', 'Hindu', 'Buddha'][rand(0, 4)],
            'address' => 'Jl. Demo No. ' . rand(1, 100) . ', RT ' . rand(1, 9) . '/RW ' . rand(1, 9),
            'city' => 'Jakarta',
            'province' => 'DKI Jakarta',
            'postal_code' => '1' . rand(1000, 9999),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Create student
        $inserted = DB::table('students')->insertOrIgnore([
            'id' => $studentId,
            'tenant_id' => $tenantId,
            'user_id' => $userId,
            'nis' => $nis,
            'nisn' => $nisn,
            'entry_date' => ($birthYear + 15) . '-07-01',
            'entry_type' => 'new',
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Student was skipped (duplicate NIS/NISN) — enrollment would violate its FK
        if ($inserted === 0) {
            return;
        }

        // Create enrollment
        DB::table('student_enrollments')->insertOrIgnore([
            'id' => Str::uuid()->toString(),
            'tenant_id' => $tenantId,
            'student_id' => $studentId,
            'academic_year_id' => $academicYearId,
            'classroom_id' => $classroomId,
            'student_number_in_class' => str_pad($number, 2, '0', STR_PAD_LEFT),
            'status' => 'active',
            'enrollment_date' => now()->subMonths(rand(1, 6)),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Assign role
        $user = User::find($userId);
        if ($user) {
            $user->assignRole('siswa');
        }
    }
}
