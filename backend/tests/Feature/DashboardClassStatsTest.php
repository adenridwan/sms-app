<?php

use App\Services\DashboardStatsService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Fase 3 ROLE-ACCESS-PLAN.md: statistik per kelas untuk dropdown kelas
 * diampu (guru) dan detail per anak (orang tua).
 */
beforeEach(function () {
    setupSchoolWorld();
    $this->service = app(DashboardStatsService::class);
});

test('classStats berisi jumlah siswa, jadwal guru, dan siswa sering alfa', function () {
    $guru = makeUser('guru.kelas', 'guru', 'teacher');
    assignClassroom($guru, $this->classroomA);
    makeScheduleFor($guru, $this->classroomA, $this->activeYearId);

    recordAttendanceToday($this->amir, $this->classroomA, 'absent');

    $stats = $this->service->classStats($guru, $this->classroomA);

    expect($stats['students_count'])->toBe(1)
        ->and($stats['my_schedules'])->toHaveCount(1)
        ->and($stats['my_schedules'][0]['subject'])->toBe('Matematika')
        ->and($stats['my_schedules'][0]['day_name'])->toBe('Senin')
        ->and($stats['top_absent'])->toHaveCount(1)
        ->and($stats['top_absent'][0]['nis'])->toBe($this->amir->nis)
        ->and($stats['top_absent'][0]['absent_days'])->toBe(1)
        ->and($stats['attendance_today']['summary']['alfa'])->toBe(1)
        ->and($stats['attendance_today']['summary']['belum_scan'])->toBe(0);
});

test('teacherStats menyertakan kelas perwalian sebagai default dropdown', function () {
    $wali = makeUser('wali.default', 'wali_kelas', 'teacher');
    assignClassroom($wali, $this->classroomB);
    DB::table('classrooms')->where('id', $this->classroomA)
        ->update(['homeroom_teacher_id' => $wali->id]);

    $stats = $this->service->statsFor($wali);

    expect($stats['homeroom_classroom_id'])->toBe($this->classroomA)
        ->and($stats['total_classes'])->toBe(2);
});

test('API class stats untuk kelas diampu mengembalikan 200', function () {
    $guru = makeUser('guru.apikelas', 'guru', 'teacher');
    assignClassroom($guru, $this->classroomA);

    $this->actingAs($guru, 'sanctum')
        ->getJson('/api/v1/dashboard/class/' . $this->classroomA)
        ->assertOk()
        ->assertJsonPath('data.students_count', 1)
        ->assertJsonPath('data.classroom_id', $this->classroomA);
});

test('API class stats kelas lain ditolak 403 untuk guru', function () {
    $guru = makeUser('guru.tolakkelas', 'guru', 'teacher');
    assignClassroom($guru, $this->classroomA);

    $this->actingAs($guru, 'sanctum')
        ->getJson('/api/v1/dashboard/class/' . $this->classroomB)
        ->assertForbidden();
});

test('API class stats bebas untuk admin', function () {
    $admin = makeUser('admin.kelas', 'admin', 'admin');

    $this->actingAs($admin, 'sanctum')
        ->getJson('/api/v1/dashboard/class/' . $this->classroomB)
        ->assertOk()
        ->assertJsonPath('data.students_count', 1);
});

test('API class stats ditolak untuk siswa', function () {
    $this->actingAs($this->amirUser, 'sanctum')
        ->getJson('/api/v1/dashboard/class/' . $this->classroomA)
        ->assertForbidden();
});

test('detail anak orang tua memuat rekap bulan dan status hari ini', function () {
    $ortu = makeUser('ortu.detail', 'orang_tua', 'parent');
    DB::table('student_guardians')->insert([
        'id' => Str::uuid()->toString(),
        'tenant_id' => $this->tenantId,
        'student_id' => $this->amir->id,
        'user_id' => $ortu->id,
        'relationship' => 'father',
        'name' => 'Ayah Amir',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    recordAttendanceToday($this->amir, $this->classroomA, 'sick');

    $stats = $this->service->statsFor($ortu);

    expect($stats['children'][0]['attendance_month']['sakit'])->toBe(1)
        ->and($stats['children'][0]['today_status'])->toBe('sick')
        ->and($stats['children'][0]['class_name'])->toBe('X A');
});
