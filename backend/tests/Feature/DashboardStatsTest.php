<?php

use App\Services\DashboardStatsService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Fase 2 ROLE-ACCESS-PLAN.md: paket dashboard per role (R5) tanpa angka
 * dummy (R6), data mengikuti scope R3/R4. Termasuk regresi T4/T5:
 * multi-role memakai prioritas, wali_kelas & orang_tua tidak lagi jatuh
 * ke paket basic.
 */
beforeEach(function () {
    setupSchoolWorld();
    $this->service = app(DashboardStatsService::class);
});

// ---------- R5: resolusi paket ----------

test('paket ditentukan role tertinggi, bukan roles->first()', function () {
    $user = makeUser('multi.role', 'guru', 'teacher');
    $user->assignRole('admin'); // urutan assignment: guru dulu, admin belakangan

    expect($this->service->resolvePackage($user->fresh()))->toBe('admin');
});

test('wali_kelas mendapat paket teacher (regresi T5)', function () {
    $wali = makeUser('wali.pkg', 'wali_kelas', 'teacher');

    expect($this->service->resolvePackage($wali))->toBe('teacher');
});

test('orang_tua mendapat paket parent (regresi bug wali_murid)', function () {
    $ortu = makeUser('ortu.pkg', 'orang_tua', 'parent');

    expect($this->service->resolvePackage($ortu))->toBe('parent');
});

test('kepala_sekolah mendapat paket principal tanpa ringkasan keuangan', function () {
    $kepsek = makeUser('kepsek.pkg', 'kepala_sekolah');
    $stats = $this->service->statsFor($kepsek);

    expect($stats['package'])->toBe('principal')
        ->and($stats)->not->toHaveKey('finance_summary')
        ->and($stats['total_students'])->toBe(2);
});

test('admin mendapat paket lengkap dengan ringkasan keuangan', function () {
    $admin = makeUser('admin.pkg', 'admin', 'admin');
    $stats = $this->service->statsFor($admin);

    expect($stats['package'])->toBe('admin')
        ->and($stats['finance_summary'])->toHaveKeys(['total_billed', 'total_collected', 'total_outstanding'])
        ->and($stats['total_students'])->toBe(2);
});

// ---------- paket guru: scope R3 ----------

test('dashboard guru hanya menghitung kelas dan siswa yang diampu', function () {
    $guru = makeUser('guru.dash', 'guru', 'teacher');
    assignClassroom($guru, $this->classroomA);

    recordAttendanceToday($this->amir, $this->classroomA, 'present');
    recordAttendanceToday($this->budi, $this->classroomB, 'present');

    $stats = $this->service->statsFor($guru);

    expect($stats['package'])->toBe('teacher')
        ->and($stats['linked'])->toBeTrue()
        ->and($stats['total_classes'])->toBe(1)
        ->and($stats['total_students'])->toBe(1)
        ->and($stats['my_classes'][0]['name'])->toBe('X A')
        // absensi hanya kelas A: 1 hadir, 0 belum scan (budi tidak ikut dihitung)
        ->and($stats['attendance_today']['summary']['hadir'])->toBe(1)
        ->and($stats['attendance_today']['summary']['belum_scan'])->toBe(0);
});

test('dashboard guru tanpa penugasan jujur kosong (R6)', function () {
    $guru = makeUser('guru.jujur', 'guru', 'teacher');
    $stats = $this->service->statsFor($guru);

    expect($stats['linked'])->toBeFalse()
        ->and($stats['my_classes'])->toBe([]);
});

// ---------- paket siswa ----------

test('dashboard siswa berisi rekap dirinya sendiri', function () {
    recordAttendanceToday($this->amir, $this->classroomA, 'present');

    $stats = $this->service->statsFor($this->amirUser);

    expect($stats['package'])->toBe('student')
        ->and($stats['linked'])->toBeTrue()
        ->and($stats['class_name'])->toBe('X A')
        ->and($stats['attendance_month']['hadir'])->toBe(1)
        ->and($stats['attendance_percentage'])->toEqual(100.0)
        ->and($stats['today_status'])->toBe('present')
        ->and($stats['unpaid_fees'])->toEqual(0.0);
});

test('status terlambat tetap dihitung hadir pada rekap siswa', function () {
    recordAttendanceToday($this->amir, $this->classroomA, 'late');

    $stats = $this->service->statsFor($this->amirUser);

    expect($stats['attendance_month']['hadir'])->toBe(1)
        ->and($stats['attendance_percentage'])->toEqual(100.0);
});

// ---------- paket orang tua ----------

test('dashboard orang tua berisi anaknya saja', function () {
    $ortu = makeUser('ortu.dash', 'orang_tua', 'parent');
    DB::table('student_guardians')->insert([
        'id' => Str::uuid()->toString(),
        'tenant_id' => $this->tenantId,
        'student_id' => $this->amir->id,
        'user_id' => $ortu->id,
        'relationship' => 'mother',
        'name' => 'Ibu Amir',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $stats = $this->service->statsFor($ortu);

    expect($stats['package'])->toBe('parent')
        ->and($stats['linked'])->toBeTrue()
        ->and($stats['children'])->toHaveCount(1)
        ->and($stats['children'][0]['nis'])->toBe($this->amir->nis);
});

test('dashboard orang tua tanpa anak tertaut jujur kosong (R6)', function () {
    $ortu = makeUser('ortu.kosong', 'orang_tua', 'parent');
    $stats = $this->service->statsFor($ortu);

    expect($stats['linked'])->toBeFalse()
        ->and($stats['children'])->toHaveCount(0);
});

// ---------- endpoint API & halaman web ----------

test('API dashboard mengembalikan paket sesuai role', function () {
    $guru = makeUser('guru.apidash', 'guru', 'teacher');
    assignClassroom($guru, $this->classroomA);

    $this->actingAs($guru, 'sanctum')
        ->getJson('/api/v1/dashboard')
        ->assertOk()
        ->assertJsonPath('data.package', 'teacher')
        ->assertJsonPath('data.total_students', 1);
});

test('route API dashboard/stats tidak lagi 500', function () {
    $admin = makeUser('admin.apistats', 'admin', 'admin');

    $this->actingAs($admin, 'sanctum')
        ->getJson('/api/v1/dashboard/stats')
        ->assertOk()
        ->assertJsonPath('data.package', 'admin');
});

test('halaman web dashboard mengirim stats nyata via Inertia', function () {
    $admin = makeUser('admin.webdash', 'admin', 'admin')->refresh();

    $this->withoutVite()
        ->actingAs($admin)
        ->get('/dashboard')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Dashboard')
            ->where('stats.package', 'admin')
            ->where('stats.total_students', 2));
});
