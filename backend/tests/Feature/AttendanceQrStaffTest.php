<?php

use App\Infrastructure\Persistence\Eloquent\Staff\Department;
use App\Infrastructure\Persistence\Eloquent\Staff\Position;
use App\Infrastructure\Persistence\Eloquent\Staff\Staff;
use App\Infrastructure\Persistence\Eloquent\Teacher\Teacher;

/**
 * Dua hal yang dijaga berkas ini:
 *
 * 1. **Regresi tab "Guru"** — `qr/teachers/bulk` dulu mengambil guru lewat
 *    `Teacher::with('user')->get()` lalu membaca `full_name`, accessor yang
 *    dirakit dari `user_profiles`. Karena modelnya diambil sebagai koleksi,
 *    Eloquent strict mode (Model::preventLazyLoading di AppServiceProvider,
 *    aktif di semua env non-produksi) melemparnya sebagai
 *    LazyLoadingViolationException — tab "Guru" balas 500 dan QR tidak pernah
 *    tergenerate. Tab "Siswa" lolos hanya karena servisnya mengambil model
 *    satu per satu lewat find(), yang dikecualikan dari aturan itu.
 *
 * 2. **Tab "Staf" baru** — sumbernya master data Staff (menu Data Staff),
 *    terpisah dari guru, dan tetap khusus pemegang settings.attendance.
 */
beforeEach(function () {
    setupSchoolWorld();

    $this->admin = makeUser('admin.qrstaf', 'admin');

    $guruUser = makeUser('guru.qrstaf', 'guru', 'teacher');
    makeTeacherRow($guruUser);
    assignClassroom($guruUser, test()->classroomA);
    $this->guru = $guruUser;

    // Guru KEDUA — bukan hiasan. Laravel hanya menandai model sebagai
    // "tidak boleh lazy load" ketika query mengembalikan lebih dari satu baris
    // (Builder::hydrate: `if (count($items) > 1)`). Dengan satu guru saja,
    // bug lazy loading yang membuat tab "Guru" balas 500 di data sungguhan
    // TIDAK akan pernah muncul di test ini. Begitu juga siswa kedua di bawah.
    makeTeacherRow(makeUser('guru.qrstaf2', 'guru', 'teacher'));
    makeStudent('citra', test()->classroomA);
});

function makeTeacherRow(\App\Infrastructure\Persistence\Eloquent\Auth\User $user): Teacher
{
    return Teacher::create([
        'tenant_id' => test()->tenantId,
        'user_id' => $user->id,
        'nip' => (string) random_int(1000000000, 9999999999),
        'join_date' => '2020-01-01',
        'status' => 'active',
    ]);
}

/** Satu baris staf aktif lengkap dengan unit & jabatannya. */
function makeStaff(string $username = 'pegawai.tu', string $status = 'active'): Staff
{
    $user = makeUser($username, 'staf');

    $department = Department::create([
        'tenant_id' => test()->tenantId,
        'name' => 'Tata Usaha',
        'code' => 'TU-' . random_int(100, 999),
        'is_active' => true,
    ]);

    $position = Position::create([
        'tenant_id' => test()->tenantId,
        'name' => 'Staf Administrasi',
        'code' => 'ADM-' . random_int(100, 999),
        'level' => 1,
        'is_active' => true,
    ]);

    return Staff::create([
        'tenant_id' => test()->tenantId,
        'user_id' => $user->id,
        'department_id' => $department->id,
        'position_id' => $position->id,
        'employee_id' => 'PEG-' . random_int(1000, 9999),
        'join_date' => '2022-01-01',
        'employment_status' => 'permanent',
        'status' => $status,
    ]);
}

test('QR massal guru berhasil digenerate, bukan 500 karena lazy loading', function () {
    $response = $this->actingAs($this->admin, 'sanctum')
        ->getJson('/api/v1/attendance/qr/teachers/bulk')
        ->assertOk();

    expect($response->json('data.count'))->toBe(2);
    expect($response->json('data.teachers.0.qr_code'))->toStartWith('data:image/svg+xml;base64,');
    expect($response->json('data.teachers.0.name'))->not->toBeEmpty();
    expect($response->json('data.teachers.1.name'))->not->toBeEmpty();
});

test('QR massal siswa tetap berhasil setelah eager load diperbaiki', function () {
    $response = $this->actingAs($this->admin, 'sanctum')
        ->getJson('/api/v1/attendance/qr/students/bulk?classroom_id=' . test()->classroomA)
        ->assertOk();

    expect($response->json('data.students'))->toHaveCount(2);
    expect($response->json('data.students.0.qr_code'))->toStartWith('data:image/svg+xml;base64,');
    expect($response->json('data.students.0.name'))->not->toBeEmpty();
    expect($response->json('data.students.1.name'))->not->toBeEmpty();
});

test('QR massal staf mengambil dari master data Staff lengkap dengan jabatan', function () {
    $staff = makeStaff();
    // Staf kedua supaya query mengembalikan >1 baris — tanpa itu aturan
    // lazy-load Eloquent tidak aktif dan test ini buta terhadap bug yang
    // sama seperti pada tab "Guru" (lihat catatan di beforeEach).
    makeStaff('pegawai.tu2');

    $response = $this->actingAs($this->admin, 'sanctum')
        ->getJson('/api/v1/attendance/qr/staff/bulk')
        ->assertOk();

    expect($response->json('data.count'))->toBe(2);

    // Urutan hasil tidak dijamin — cari barisnya, jangan mengunci ke index 0.
    $row = collect($response->json('data.staff'))->firstWhere('staff_id', $staff->id);

    expect($row)->not->toBeNull();
    expect($row['employee_id'])->toBe($staff->employee_id);
    expect($row['position'])->toBe('Staf Administrasi');
    expect($row['department'])->toBe('Tata Usaha');
    expect($row['employment_status_label'])->toBe('Tetap');
    expect($row['name'])->not->toBeEmpty();
    expect($row['qr_code'])->toStartWith('data:image/svg+xml;base64,');
});

test('staf non-aktif tidak ikut terbawa ke daftar QR', function () {
    makeStaff('pegawai.resign', 'terminated');

    $this->actingAs($this->admin, 'sanctum')
        ->getJson('/api/v1/attendance/qr/staff/bulk')
        ->assertOk()
        ->assertJsonPath('data.count', 0);
});

test('staf tanpa unique_code otomatis mendapat kode saat QR dimuat', function () {
    $staff = makeStaff();
    // Baris lama (dibuat sebelum kolom unique_code ada) bisa NULL — lewat
    // query builder supaya hook creating di model tidak ikut mengisinya.
    Staff::withoutGlobalScopes()->whereKey($staff->id)->update(['unique_code' => null]);

    $response = $this->actingAs($this->admin, 'sanctum')
        ->getJson('/api/v1/attendance/qr/staff/bulk')
        ->assertOk();

    expect($response->json('data.staff.0.unique_code'))->toStartWith('STF-');
    expect($staff->fresh()->unique_code)->toStartWith('STF-');
});

test('regenerate QR staf mengganti unique_code lamanya', function () {
    $staff = makeStaff();
    $oldCode = $staff->unique_code;

    $response = $this->actingAs($this->admin, 'sanctum')
        ->postJson("/api/v1/attendance/qr/staff/{$staff->id}/regenerate")
        ->assertOk();

    expect($response->json('data.old_code'))->toBe($oldCode);
    expect($response->json('data.new_code'))->not->toBe($oldCode);
    expect($staff->fresh()->unique_code)->toBe($response->json('data.new_code'));
});

test('QR staf tunggal bisa diambil dan diunduh', function () {
    $staff = makeStaff();

    $this->actingAs($this->admin, 'sanctum')
        ->getJson("/api/v1/attendance/qr/staff/{$staff->id}")
        ->assertOk()
        ->assertJsonPath('data.employee_id', $staff->employee_id);

    $this->actingAs($this->admin, 'sanctum')
        ->getJson("/api/v1/attendance/qr/staff/{$staff->id}/download")
        ->assertOk()
        ->assertJsonPath('data.unique_code', $staff->unique_code);
});

test('guru tidak bisa menyentuh QR staf sama sekali', function () {
    $staff = makeStaff();

    $this->actingAs($this->guru, 'sanctum')
        ->getJson('/api/v1/attendance/qr/staff/bulk')
        ->assertForbidden();

    $this->actingAs($this->guru, 'sanctum')
        ->getJson("/api/v1/attendance/qr/staff/{$staff->id}")
        ->assertForbidden();

    $this->actingAs($this->guru, 'sanctum')
        ->postJson("/api/v1/attendance/qr/staff/{$staff->id}/regenerate")
        ->assertForbidden();

    $this->actingAs($this->guru, 'sanctum')
        ->getJson("/api/v1/attendance/qr/staff/{$staff->id}/download")
        ->assertForbidden();
});

test('rute qr/staff/bulk tidak tertangkap wildcard qr/staff/{staff}', function () {
    // Jebakan yang sama pernah membuat qr/teachers/bulk 500 karena "bulk"
    // dianggap id — urutan pendaftaran rute wajib bulk dulu, baru wildcard.
    makeStaff();

    $this->actingAs($this->admin, 'sanctum')
        ->getJson('/api/v1/attendance/qr/staff/bulk')
        ->assertOk()
        ->assertJsonStructure(['data' => ['count', 'staff']]);
});
