<?php

/**
 * Pembangun "dunia uji" bersama untuk test Fase 1 & 2 (ROLE-ACCESS-PLAN.md):
 * 1 tenant, tahun ajaran aktif + non-aktif, 2 kelas (A & B),
 * siswa Amir di kelas A dan Budi di kelas B.
 *
 * Fungsi-fungsi ini mengakses test case aktif lewat test().
 */

use App\Infrastructure\Persistence\Eloquent\Auth\User;
use App\Infrastructure\Persistence\Eloquent\Student\Student;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Spatie\Permission\PermissionRegistrar;

function setupSchoolWorld(): void
{
    test()->seed([PermissionSeeder::class, RoleSeeder::class]);
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    test()->tenantId = Str::uuid()->toString();
    DB::table('tenants')->insert([
        'id' => test()->tenantId,
        'name' => 'Sekolah Uji',
        'slug' => 'sekolah-uji',
        'email' => 'uji@sekolah.test',
        'status' => 'active',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    test()->activeYearId = makeAcademicYear(true, '2025/2026');
    test()->inactiveYearId = makeAcademicYear(false, '2024/2025');

    test()->semesterId = Str::uuid()->toString();
    DB::table('semesters')->insert([
        'id' => test()->semesterId,
        'tenant_id' => test()->tenantId,
        'academic_year_id' => test()->activeYearId,
        'name' => 'Semester 1',
        'number' => 1,
        'start_date' => '2025-07-01',
        'end_date' => '2025-12-31',
        'is_active' => true,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    test()->gradeLevelId = Str::uuid()->toString();
    DB::table('grade_levels')->insert([
        'id' => test()->gradeLevelId,
        'tenant_id' => test()->tenantId,
        'name' => 'Kelas 10',
        'code' => 'X',
        'order' => 1,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    test()->classroomA = makeClassroom(test()->gradeLevelId, 'X A', test()->activeYearId);
    test()->classroomB = makeClassroom(test()->gradeLevelId, 'X B', test()->activeYearId);

    [test()->amir, test()->amirUser] = makeStudent('amir', test()->classroomA);
    [test()->budi, test()->budiUser] = makeStudent('budi', test()->classroomB);
}

function makeAcademicYear(bool $active, string $name): string
{
    $id = Str::uuid()->toString();
    DB::table('academic_years')->insert([
        'id' => $id,
        'tenant_id' => test()->tenantId,
        'name' => $name,
        'start_date' => '2025-07-01',
        'end_date' => '2026-06-30',
        'is_active' => $active,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    return $id;
}

function makeClassroom(string $gradeLevelId, string $name, string $academicYearId): string
{
    $id = Str::uuid()->toString();
    DB::table('classrooms')->insert([
        'id' => $id,
        'tenant_id' => test()->tenantId,
        'academic_year_id' => $academicYearId,
        'grade_level_id' => $gradeLevelId,
        'name' => $name,
        'code' => Str::slug($name),
        'is_active' => true,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    return $id;
}

function makeUser(string $username, string $role, string $userType = 'staff'): User
{
    $user = User::create([
        'tenant_id' => test()->tenantId,
        'username' => $username,
        'email' => $username . '@sekolah.test',
        'password' => 'password',
        'status' => 'active',
        'user_type' => $userType,
    ]);
    $user->assignRole($role);

    // Model::preventAccessingMissingAttributes() (aktif di testing) melempar
    // error saat kolom yang tak dikirim ke create() (mis. 'avatar') diakses
    // nanti — misalnya oleh HandleInertiaRequests saat actingAs() dipakai
    // langsung ke halaman web. fresh() mengambil ulang baris penuh dari DB,
    // persis seperti yang dilakukan SessionGuard pada request sungguhan.
    return $user->fresh();
}

/** @return array{0: Student, 1: User} siswa + akun usernya, terdaftar aktif di kelas */
function makeStudent(string $username, string $classroomId): array
{
    $user = makeUser($username, 'siswa', 'student');

    $student = Student::create([
        'tenant_id' => test()->tenantId,
        'user_id' => $user->id,
        'nis' => (string) random_int(10000000, 99999999),
        'entry_date' => '2025-07-01',
        'entry_type' => 'new',
        'status' => 'active',
    ]);

    DB::table('student_enrollments')->insert([
        'id' => Str::uuid()->toString(),
        'tenant_id' => test()->tenantId,
        'student_id' => $student->id,
        'academic_year_id' => test()->activeYearId,
        'classroom_id' => $classroomId,
        'status' => 'active',
        'enrollment_date' => now(),
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    return [$student, $user];
}

function assignClassroom(User $teacher, string $classroomId, ?string $academicYearId = null): void
{
    DB::table('teacher_classrooms')->insert([
        'id' => Str::uuid()->toString(),
        'tenant_id' => test()->tenantId,
        'teacher_id' => $teacher->id,
        'classroom_id' => $classroomId,
        'academic_year_id' => $academicYearId ?? test()->activeYearId,
        'created_at' => now(),
        'updated_at' => now(),
    ]);
}

function makeScheduleFor(User $teacher, string $classroomId, string $academicYearId): void
{
    $semesterId = DB::table('semesters')->where('academic_year_id', $academicYearId)->value('id');
    if (! $semesterId) {
        $semesterId = Str::uuid()->toString();
        DB::table('semesters')->insert([
            'id' => $semesterId,
            'tenant_id' => test()->tenantId,
            'academic_year_id' => $academicYearId,
            'name' => 'Semester 1',
            'number' => 1,
            'start_date' => '2025-07-01',
            'end_date' => '2025-12-31',
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    $subjectId = Str::uuid()->toString();
    DB::table('subjects')->insert([
        'id' => $subjectId,
        'tenant_id' => test()->tenantId,
        'name' => 'Matematika',
        'code' => 'MTK-' . Str::random(4),
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $timeSlotId = Str::uuid()->toString();
    DB::table('time_slots')->insert([
        'id' => $timeSlotId,
        'tenant_id' => test()->tenantId,
        'name' => 'Jam 1',
        'start_time' => '07:00',
        'end_time' => '07:45',
        'order' => 1,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    DB::table('schedules')->insert([
        'id' => Str::uuid()->toString(),
        'tenant_id' => test()->tenantId,
        'academic_year_id' => $academicYearId,
        'semester_id' => $semesterId,
        'classroom_id' => $classroomId,
        'subject_id' => $subjectId,
        'teacher_id' => $teacher->id,
        'time_slot_id' => $timeSlotId,
        'day_of_week' => 1,
        'is_active' => true,
        'created_at' => now(),
        'updated_at' => now(),
    ]);
}

/** Catat absensi hari ini untuk siswa (status DB berbahasa Inggris). */
function recordAttendanceToday(Student $student, string $classroomId, string $status = 'present'): void
{
    DB::table('student_attendances')->insert([
        'id' => Str::uuid()->toString(),
        'tenant_id' => test()->tenantId,
        'student_id' => $student->id,
        'classroom_id' => $classroomId,
        'academic_year_id' => test()->activeYearId,
        'semester_id' => test()->semesterId,
        'attendance_date' => now()->toDateString(),
        'status' => $status,
        'created_at' => now(),
        'updated_at' => now(),
    ]);
}

function visibleNisFor(User $user): array
{
    return Student::visibleTo($user)->pluck('nis')->sort()->values()->all();
}
