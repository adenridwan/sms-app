<?php

use App\Infrastructure\Persistence\Eloquent\Academic\AcademicYear;
use App\Infrastructure\Persistence\Eloquent\Academic\Semester;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Fase 0 pembenahan modul Akademik: endpoint tahun ajaran & semester dulu
 * rusak diam-diam — route-model binding tidak pernah cocok ({year} vs
 * $academicYear), route `activate` menunjuk method yang tidak ada, dan
 * semester dikirim sebagai `semester_number` padahal kolomnya `number`.
 */
beforeEach(function () {
    setupSchoolWorld();
    $this->admin = makeUser('admin.tahun', 'admin', 'admin');
});

// ---------- route-model binding ----------

test('detail tahun ajaran mengembalikan baris yang diminta', function () {
    $response = $this->actingAs($this->admin, 'sanctum')
        ->getJson("/api/v1/academic/years/{$this->inactiveYearId}");

    $response->assertOk()
        ->assertJsonPath('data.id', $this->inactiveYearId)
        ->assertJsonPath('data.name', '2024/2025');
});

test('ubah tahun ajaran menyimpan perubahan ke baris yang benar', function () {
    $this->actingAs($this->admin, 'sanctum')
        ->putJson("/api/v1/academic/years/{$this->inactiveYearId}", ['name' => '2024/2025 Revisi'])
        ->assertOk()
        ->assertJsonPath('data.name', '2024/2025 Revisi');

    expect(AcademicYear::find($this->inactiveYearId)->name)->toBe('2024/2025 Revisi');
});

test('ubah hanya tanggal selesai tidak tertolak validasi after:start_date', function () {
    $this->actingAs($this->admin, 'sanctum')
        ->putJson("/api/v1/academic/years/{$this->inactiveYearId}", ['end_date' => '2026-07-31'])
        ->assertOk()
        ->assertJsonPath('data.end_date', '2026-07-31');
});

test('hapus tahun ajaran nonaktif tanpa kelas berhasil', function () {
    $kosong = AcademicYear::create([
        'tenant_id' => $this->tenantId,
        'name' => '2023/2024',
        'start_date' => '2023-07-01',
        'end_date' => '2024-06-30',
        'is_active' => false,
    ]);

    $this->actingAs($this->admin, 'sanctum')
        ->deleteJson("/api/v1/academic/years/{$kosong->id}")
        ->assertOk();

    expect(AcademicYear::find($kosong->id))->toBeNull();
});

test('tahun ajaran aktif dan yang masih punya kelas tidak bisa dihapus', function () {
    $this->actingAs($this->admin, 'sanctum')
        ->deleteJson("/api/v1/academic/years/{$this->activeYearId}")
        ->assertStatus(422);

    expect(AcademicYear::find($this->activeYearId))->not->toBeNull();
});

// ---------- penjagaan referensi saat hapus ----------

/** Tahun ajaran kosong yang aman dipakai sebagai sasaran percobaan hapus. */
function makeDeletableYear(): AcademicYear
{
    return AcademicYear::create([
        'tenant_id' => test()->tenantId,
        'name' => '2022/2023',
        'start_date' => '2022-07-01',
        'end_date' => '2023-06-30',
        'is_active' => false,
    ]);
}

test('tahun ajaran tidak bisa dihapus bila masih dipakai pendaftaran siswa', function () {
    $tahun = makeDeletableYear();

    DB::table('student_enrollments')->insert([
        'id' => Str::uuid()->toString(),
        'tenant_id' => $this->tenantId,
        'student_id' => $this->amir->id,
        'academic_year_id' => $tahun->id,
        'classroom_id' => $this->classroomA,
        'enrollment_date' => '2022-07-01',
        'status' => 'active',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $this->actingAs($this->admin, 'sanctum')
        ->deleteJson("/api/v1/academic/years/{$tahun->id}")
        ->assertStatus(422)
        ->assertJsonPath('message', 'Tahun ajaran tidak dapat dihapus karena masih digunakan oleh data pendaftaran siswa.');

    expect(AcademicYear::find($tahun->id))->not->toBeNull();
});

test('tahun ajaran tidak bisa dihapus bila masih dipakai absensi dan penempatan guru', function () {
    $tahun = makeDeletableYear();
    $kelas = makeClassroom($this->gradeLevelId, 'X Arsip', $tahun->id);

    DB::table('student_attendances')->insert([
        'id' => Str::uuid()->toString(),
        'tenant_id' => $this->tenantId,
        'student_id' => $this->amir->id,
        'classroom_id' => $kelas,
        'academic_year_id' => $tahun->id,
        'semester_id' => $this->semesterId,
        'attendance_date' => '2022-08-01',
        'status' => 'present',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    DB::table('teacher_classrooms')->insert([
        'id' => Str::uuid()->toString(),
        'tenant_id' => $this->tenantId,
        'teacher_id' => $this->admin->id,
        'classroom_id' => $kelas,
        'academic_year_id' => $tahun->id,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    // Pesannya menyebut SEMUA jenis data yang menahan, bukan hanya yang pertama.
    $this->actingAs($this->admin, 'sanctum')
        ->deleteJson("/api/v1/academic/years/{$tahun->id}")
        ->assertStatus(422)
        ->assertJsonPath(
            'message',
            'Tahun ajaran tidak dapat dihapus karena masih digunakan oleh data kelas, penempatan guru, absensi siswa.'
        );

    expect(AcademicYear::find($tahun->id))->not->toBeNull();
});

test('tahun ajaran tidak bisa dihapus bila semesternya masih memegang nilai siswa', function () {
    $tahun = makeDeletableYear();

    $semester = Semester::create([
        'tenant_id' => $this->tenantId,
        'academic_year_id' => $tahun->id,
        'name' => 'Semester Ganjil',
        'number' => 1,
        'start_date' => '2022-07-01',
        'end_date' => '2022-12-31',
        'is_active' => false,
    ]);

    $mapelId = Str::uuid()->toString();
    DB::table('subjects')->insert([
        'id' => $mapelId,
        'tenant_id' => $this->tenantId,
        'name' => 'Matematika',
        'code' => 'MTK-' . Str::random(4),
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    // final_grades hanya punya semester_id — tanpa penelusuran lewat semester,
    // tahun ajaran ini lolos dihapus dan nilainya menggantung.
    DB::table('final_grades')->insert([
        'id' => Str::uuid()->toString(),
        'tenant_id' => $this->tenantId,
        'student_id' => $this->amir->id,
        'subject_id' => $mapelId,
        'classroom_id' => $this->classroomA,
        'semester_id' => $semester->id,
        'final_score' => 80,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $this->actingAs($this->admin, 'sanctum')
        ->deleteJson("/api/v1/academic/years/{$tahun->id}")
        ->assertStatus(422)
        ->assertJsonPath('message', 'Tahun ajaran tidak dapat dihapus karena masih digunakan oleh data nilai akhir.');

    expect(AcademicYear::find($tahun->id))->not->toBeNull()
        ->and(Semester::find($semester->id))->not->toBeNull();
});

test('data pemakai yang sudah di-soft delete tidak lagi menahan penghapusan', function () {
    $tahun = makeDeletableYear();

    $kelasId = makeClassroom($this->gradeLevelId, 'X Lama', $tahun->id);
    DB::table('classrooms')->where('id', $kelasId)->update(['deleted_at' => now()]);

    $this->actingAs($this->admin, 'sanctum')
        ->deleteJson("/api/v1/academic/years/{$tahun->id}")
        ->assertOk();

    expect(AcademicYear::find($tahun->id))->toBeNull();
});

test('semester tidak bisa dihapus bila masih dipakai absensi', function () {
    $semester = Semester::create([
        'tenant_id' => $this->tenantId,
        'academic_year_id' => $this->inactiveYearId,
        'name' => 'Semester Genap',
        'number' => 2,
        'start_date' => '2025-01-01',
        'end_date' => '2025-06-30',
        'is_active' => false,
    ]);

    DB::table('student_attendances')->insert([
        'id' => Str::uuid()->toString(),
        'tenant_id' => $this->tenantId,
        'student_id' => $this->amir->id,
        'classroom_id' => $this->classroomA,
        'academic_year_id' => $this->inactiveYearId,
        'semester_id' => $semester->id,
        'attendance_date' => '2025-02-03',
        'status' => 'present',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $this->actingAs($this->admin, 'sanctum')
        ->deleteJson("/api/v1/academic/semesters/{$semester->id}")
        ->assertStatus(422)
        ->assertJsonPath('message', 'Semester tidak dapat dihapus karena masih digunakan oleh data absensi siswa.');

    expect(Semester::find($semester->id))->not->toBeNull();
});

test('semester tanpa data pemakai tetap bisa dihapus', function () {
    $semester = Semester::create([
        'tenant_id' => $this->tenantId,
        'academic_year_id' => $this->inactiveYearId,
        'name' => 'Semester Genap',
        'number' => 2,
        'start_date' => '2025-01-01',
        'end_date' => '2025-06-30',
        'is_active' => false,
    ]);

    $this->actingAs($this->admin, 'sanctum')
        ->deleteJson("/api/v1/academic/semesters/{$semester->id}")
        ->assertOk();

    expect(Semester::find($semester->id))->toBeNull();
});

// ---------- aktivasi ----------

test('aktifkan tahun ajaran memindahkan status aktif', function () {
    $this->actingAs($this->admin, 'sanctum')
        ->postJson("/api/v1/academic/years/{$this->inactiveYearId}/activate")
        ->assertOk()
        ->assertJsonPath('data.is_active', true);

    expect(AcademicYear::find($this->inactiveYearId)->is_active)->toBeTrue()
        ->and(AcademicYear::find($this->activeYearId)->is_active)->toBeFalse();
});

test('aktifkan tahun ajaran ikut mengaktifkan semester pertamanya', function () {
    $ganjil = Semester::create([
        'tenant_id' => $this->tenantId,
        'academic_year_id' => $this->inactiveYearId,
        'name' => 'Semester Ganjil',
        'number' => 1,
        'start_date' => '2024-07-01',
        'end_date' => '2024-12-31',
        'is_active' => false,
    ]);

    $this->actingAs($this->admin, 'sanctum')
        ->postJson("/api/v1/academic/years/{$this->inactiveYearId}/activate")
        ->assertOk();

    expect(Semester::find($ganjil->id)->is_active)->toBeTrue()
        // Semester tahun lama dilepas agar tidak menggantung di luar tahun aktif.
        ->and(Semester::find($this->semesterId)->is_active)->toBeFalse();
});

// ---------- pembuatan tahun ajaran ----------

test('tahun ajaran baru bisa langsung dibuatkan semester ganjil & genap', function () {
    $response = $this->actingAs($this->admin, 'sanctum')
        ->postJson('/api/v1/academic/years', [
            'name' => '2026/2027',
            'start_date' => '2026-07-01',
            'end_date' => '2027-06-30',
            'create_semesters' => true,
        ]);

    $response->assertCreated();

    $semesters = collect($response->json('data.semesters'));
    expect($semesters)->toHaveCount(2)
        ->and($semesters->pluck('semester_number')->all())->toBe([1, 2])
        ->and($semesters->firstWhere('semester_number', 1)['end_date'])->toBe('2026-12-31')
        ->and($semesters->firstWhere('semester_number', 2)['start_date'])->toBe('2027-01-01');
});

test('nama tahun ajaran yang sama di sekolah lain tidak memblokir sekolah ini', function () {
    $lain = Str::uuid()->toString();
    DB::table('tenants')->insert([
        'id' => $lain,
        'name' => 'Sekolah Lain',
        'slug' => 'sekolah-lain',
        'email' => 'lain@sekolah.test',
        'status' => 'active',
        'created_at' => now(),
        'updated_at' => now(),
    ]);
    DB::table('academic_years')->insert([
        'id' => Str::uuid()->toString(),
        'tenant_id' => $lain,
        'name' => '2026/2027',
        'start_date' => '2026-07-01',
        'end_date' => '2027-06-30',
        'is_active' => false,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $this->actingAs($this->admin, 'sanctum')
        ->postJson('/api/v1/academic/years', [
            'name' => '2026/2027',
            'start_date' => '2026-07-01',
            'end_date' => '2027-06-30',
        ])
        ->assertCreated();
});

test('nama tahun ajaran ganda dalam satu sekolah ditolak 422 dengan pesan yang jelas', function () {
    $this->actingAs($this->admin, 'sanctum')
        ->postJson('/api/v1/academic/years', [
            'name' => '2025/2026',
            'start_date' => '2025-07-01',
            'end_date' => '2026-06-30',
        ])
        ->assertStatus(422)
        // Bukan "Validation failed" generik — pesannya langsung bisa ditampilkan.
        ->assertJsonPath('message', 'Tahun ajaran dengan nama ini sudah ada.');
});

/**
 * Unique index [tenant_id, name] tetap dipegang baris soft-deleted, jadi nama
 * yang pernah dipakai dulu terkunci selamanya (create-nya 500 di level DB).
 */
test('nama tahun ajaran yang sudah dihapus bisa dipakai lagi', function () {
    $tahun = makeDeletableYear();

    $this->actingAs($this->admin, 'sanctum')
        ->deleteJson("/api/v1/academic/years/{$tahun->id}")
        ->assertOk();

    $this->actingAs($this->admin, 'sanctum')
        ->postJson('/api/v1/academic/years', [
            'name' => '2022/2023',
            'start_date' => '2022-07-01',
            'end_date' => '2023-06-30',
        ])
        ->assertCreated()
        ->assertJsonPath('data.name', '2022/2023');
});

test('nomor semester yang sudah dihapus bisa dipakai lagi di tahun yang sama', function () {
    $semester = Semester::create([
        'tenant_id' => $this->tenantId,
        'academic_year_id' => $this->inactiveYearId,
        'name' => 'Semester Ganjil',
        'number' => 1,
        'start_date' => '2024-07-01',
        'end_date' => '2024-12-31',
        'is_active' => false,
    ]);

    $this->actingAs($this->admin, 'sanctum')
        ->deleteJson("/api/v1/academic/semesters/{$semester->id}")
        ->assertOk();

    $this->actingAs($this->admin, 'sanctum')
        ->postJson('/api/v1/academic/semesters', [
            'academic_year_id' => $this->inactiveYearId,
            'name' => 'Semester Ganjil',
            'semester_number' => 1,
            'start_date' => '2024-07-01',
            'end_date' => '2024-12-31',
        ])
        ->assertCreated();
});

// ---------- semester ----------

test('semester baru menyimpan semester_number ke kolom number', function () {
    $response = $this->actingAs($this->admin, 'sanctum')
        ->postJson('/api/v1/academic/semesters', [
            'academic_year_id' => $this->inactiveYearId,
            'name' => 'Semester Genap',
            'semester_number' => 2,
            'start_date' => '2025-01-01',
            'end_date' => '2025-06-30',
        ]);

    $response->assertCreated()->assertJsonPath('data.semester_number', 2);

    expect(DB::table('semesters')->where('id', $response->json('data.id'))->value('number'))->toBe(2);
});

test('semester dengan nomor yang sudah ada di tahun tersebut ditolak', function () {
    $this->actingAs($this->admin, 'sanctum')
        ->postJson('/api/v1/academic/semesters', [
            'academic_year_id' => $this->activeYearId,
            'name' => 'Semester Ganjil Lagi',
            'semester_number' => 1,
            'start_date' => '2025-07-01',
            'end_date' => '2025-12-31',
        ])
        ->assertStatus(422);
});

test('aktifkan semester ikut mengaktifkan tahun ajarannya', function () {
    $genap = Semester::create([
        'tenant_id' => $this->tenantId,
        'academic_year_id' => $this->inactiveYearId,
        'name' => 'Semester Genap',
        'number' => 2,
        'start_date' => '2025-01-01',
        'end_date' => '2025-06-30',
        'is_active' => false,
    ]);

    $this->actingAs($this->admin, 'sanctum')
        ->postJson("/api/v1/academic/semesters/{$genap->id}/activate")
        ->assertOk()
        ->assertJsonPath('data.is_active', true);

    expect(AcademicYear::find($this->inactiveYearId)->is_active)->toBeTrue()
        ->and(Semester::find($this->semesterId)->is_active)->toBeFalse();
});

// ---------- otorisasi ----------

test('guru ditolak mengelola tahun ajaran', function () {
    $guru = makeUser('guru.tahun', 'guru', 'teacher');

    $this->actingAs($guru, 'sanctum')
        ->postJson('/api/v1/academic/years', [
            'name' => '2027/2028',
            'start_date' => '2027-07-01',
            'end_date' => '2028-06-30',
        ])
        ->assertForbidden();

    $this->actingAs($guru, 'sanctum')
        ->deleteJson("/api/v1/academic/years/{$this->inactiveYearId}")
        ->assertForbidden();

    $this->actingAs($guru, 'sanctum')
        ->postJson("/api/v1/academic/years/{$this->inactiveYearId}/activate")
        ->assertForbidden();
});
