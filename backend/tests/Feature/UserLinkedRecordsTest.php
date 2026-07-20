<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Fase 4 ROLE-ACCESS-PLAN.md (R1 + R8): pembuatan user dengan record
 * tertaut (teachers/staff) dalam satu transaksi, dan penautan akun
 * orang tua ke siswa via student_guardians.
 */
beforeEach(function () {
    setupSchoolWorld();
    $this->superadmin = makeUser('sa.uji', 'super_admin', 'super_admin');
});

function createUserPayload(array $overrides = []): array
{
    return array_merge([
        'username' => 'user.' . Str::random(6),
        'email' => Str::random(8) . '@sekolah.test',
        'password' => 'rahasia123',
        'first_name' => 'Uji',
        'last_name' => 'Fase Empat',
        'phone' => '0811223344',
        'status' => 'active',
    ], $overrides);
}

// ---------- R1: seksi guru & staf ----------

test('membuat user guru sekaligus membuat record teachers', function () {
    $payload = createUserPayload([
        'user_type' => 'teacher',
        'roles' => ['guru'],
        'teacher' => ['nip' => '199001012020121001', 'employment_status' => 'contract', 'education_level' => 'S1'],
    ]);

    $response = $this->actingAs($this->superadmin, 'sanctum')
        ->postJson('/api/v1/admin/users', $payload);

    $response->assertCreated()
        ->assertJsonPath('data.teacher.nip', '199001012020121001')
        ->assertJsonPath('data.teacher.employment_status', 'contract');

    $userId = $response->json('data.id');
    expect(DB::table('teachers')->where('user_id', $userId)->exists())->toBeTrue();
});

test('user rangkap: seksi guru dan staf bisa dibuat bersamaan', function () {
    $payload = createUserPayload([
        'user_type' => 'staff',
        'roles' => ['kepala_sekolah'],
        'teacher' => ['nip' => '197705052005011002'],
        'staff' => ['employee_id' => 'STF-001', 'employment_status' => 'permanent'],
    ]);

    $response = $this->actingAs($this->superadmin, 'sanctum')
        ->postJson('/api/v1/admin/users', $payload);

    $response->assertCreated()
        ->assertJsonPath('data.staff.employee_id', 'STF-001');

    $userId = $response->json('data.id');
    expect(DB::table('teachers')->where('user_id', $userId)->exists())->toBeTrue()
        ->and(DB::table('staff')->where('user_id', $userId)->exists())->toBeTrue();
});

test('lengkapi data: update user lama menambahkan record teachers', function () {
    $guru = makeUser('guru.lama.f4', 'guru', 'teacher');
    expect(DB::table('teachers')->where('user_id', $guru->id)->exists())->toBeFalse();

    $this->actingAs($this->superadmin, 'sanctum')
        ->putJson('/api/v1/admin/users/' . $guru->id, [
            'teacher' => ['nip' => '198812312015031003'],
        ])
        ->assertOk()
        ->assertJsonPath('data.teacher.nip', '198812312015031003');

    expect(DB::table('teachers')->where('user_id', $guru->id)->value('nip'))->toBe('198812312015031003');
});

test('NIP duplikat ditolak 422', function () {
    $guruA = makeUser('guru.nipa', 'guru', 'teacher');
    $this->actingAs($this->superadmin, 'sanctum')
        ->putJson('/api/v1/admin/users/' . $guruA->id, ['teacher' => ['nip' => 'NIP-SAMA']])
        ->assertOk();

    $guruB = makeUser('guru.nipb', 'guru', 'teacher');
    $this->actingAs($this->superadmin, 'sanctum')
        ->putJson('/api/v1/admin/users/' . $guruB->id, ['teacher' => ['nip' => 'NIP-SAMA']])
        ->assertStatus(422);
});

// ---------- R8: penautan orang tua ↔ siswa ----------

test('membuat akun ortu dengan pemilihan siswa menautkan student_guardians', function () {
    $payload = createUserPayload([
        'user_type' => 'parent',
        'roles' => ['orang_tua'],
        'guardian_students' => [
            ['student_id' => $this->amir->id, 'relationship' => 'father', 'is_primary_contact' => true],
        ],
    ]);

    $response = $this->actingAs($this->superadmin, 'sanctum')
        ->postJson('/api/v1/admin/users', $payload);

    $response->assertCreated()
        ->assertJsonPath('data.guardian_students.0.student_id', $this->amir->id)
        ->assertJsonPath('data.guardian_students.0.relationship', 'father');

    $ortu = \App\Infrastructure\Persistence\Eloquent\Auth\User::find($response->json('data.id'));

    // Scope R4: ortu langsung melihat anaknya
    expect(visibleNisFor($ortu))->toBe([$this->amir->nis]);
});

test('baris wali lama tanpa akun ditautkan ulang, bukan diduplikasi', function () {
    // data wali sudah ada sejak pendaftaran siswa (tanpa akun)
    DB::table('student_guardians')->insert([
        'id' => Str::uuid()->toString(),
        'tenant_id' => $this->tenantId,
        'student_id' => $this->amir->id,
        'user_id' => null,
        'relationship' => 'mother',
        'name' => 'Uji Fase Empat',
        'phone' => '0811223344',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $payload = createUserPayload([
        'user_type' => 'parent',
        'roles' => ['orang_tua'],
        'guardian_students' => [
            ['student_id' => $this->amir->id, 'relationship' => 'mother'],
        ],
    ]);

    $response = $this->actingAs($this->superadmin, 'sanctum')
        ->postJson('/api/v1/admin/users', $payload);
    $response->assertCreated();

    $rows = DB::table('student_guardians')->where('student_id', $this->amir->id)->get();
    expect($rows)->toHaveCount(1)
        ->and($rows[0]->user_id)->toBe($response->json('data.id'));
});

test('melepas tautan mengosongkan user_id tanpa menghapus baris wali', function () {
    $payload = createUserPayload([
        'user_type' => 'parent',
        'roles' => ['orang_tua'],
        'guardian_students' => [
            ['student_id' => $this->amir->id, 'relationship' => 'guardian'],
        ],
    ]);
    $response = $this->actingAs($this->superadmin, 'sanctum')
        ->postJson('/api/v1/admin/users', $payload);
    $ortuId = $response->json('data.id');

    // lepas semua anak
    $this->actingAs($this->superadmin, 'sanctum')
        ->putJson('/api/v1/admin/users/' . $ortuId, ['guardian_students' => []])
        ->assertOk();

    $row = DB::table('student_guardians')->where('student_id', $this->amir->id)->first();
    expect($row)->not->toBeNull()
        ->and($row->user_id)->toBeNull();
});

// ---------- pemilih siswa ----------

test('pencarian opsi siswa mengembalikan nama, NIS, dan kelas', function () {
    $response = $this->actingAs($this->superadmin, 'sanctum')
        ->getJson('/api/v1/admin/users/student-options?search=' . $this->amir->nis);

    $response->assertOk();
    expect($response->json('data'))->toHaveCount(1)
        ->and($response->json('data.0.nis'))->toBe($this->amir->nis)
        ->and($response->json('data.0.class_name'))->toBe('X A');
});

test('endpoint opsi siswa ditolak untuk admin biasa', function () {
    $admin = makeUser('admin.opt', 'admin', 'admin');

    $this->actingAs($admin, 'sanctum')
        ->getJson('/api/v1/admin/users/student-options?search=x')
        ->assertForbidden();
});
