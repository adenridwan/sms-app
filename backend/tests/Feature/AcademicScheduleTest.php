<?php

use App\Infrastructure\Persistence\Eloquent\Academic\Schedule;
use App\Infrastructure\Persistence\Eloquent\Academic\Subject;
use App\Infrastructure\Persistence\Eloquent\Academic\TimeSlot;

/**
 * Jadwal sebelumnya cuma skema tanpa model/controller/halaman yang benar
 * (ScheduleController lama mengacu model & kolom yang tidak ada — lihat
 * docs/academic/02-ANALISA-UIUX-JADWAL.md). Test ini menutup jalur web + API
 * yang baru diimplementasikan.
 */
beforeEach(function () {
    setupSchoolWorld();
    $this->admin = makeUser('admin.jadwal', 'admin', 'admin');
    $this->guru1 = makeUser('guru.jadwal1', 'guru', 'teacher');
    $this->guru2 = makeUser('guru.jadwal2', 'guru', 'teacher');

    $this->subject = Subject::create([
        'tenant_id' => $this->tenantId, 'name' => 'Matematika', 'code' => 'MTK', 'category' => 'Wajib',
    ]);
    $this->timeSlot = TimeSlot::create([
        'tenant_id' => $this->tenantId, 'name' => 'Jam 1', 'start_time' => '07:00', 'end_time' => '07:45', 'order' => 1,
    ]);
});

// ---------- rute web ----------

test('halaman /academic/schedules merender 200', function () {
    $this->actingAs($this->admin, 'sanctum')->get('/academic/schedules')->assertOk();
});

// ---------- API: jam pelajaran ----------

test('jam pelajaran baru bisa dibuat dan terurut sesuai order', function () {
    TimeSlot::create(['tenant_id' => $this->tenantId, 'name' => 'Jam 0', 'start_time' => '06:30', 'end_time' => '07:00', 'order' => 0]);

    $response = $this->actingAs($this->admin, 'sanctum')
        ->getJson('/api/v1/academic/time-slots');

    $response->assertOk();
    $names = collect($response->json('data.data'))->pluck('name');
    expect($names->first())->toBe('Jam 0');
});

test('jam pelajaran tidak bisa dihapus bila masih dipakai jadwal', function () {
    Schedule::create([
        'tenant_id' => $this->tenantId,
        'academic_year_id' => $this->activeYearId,
        'semester_id' => $this->semesterId,
        'classroom_id' => $this->classroomA,
        'subject_id' => $this->subject->id,
        'teacher_id' => $this->guru1->id,
        'time_slot_id' => $this->timeSlot->id,
        'day_of_week' => 1,
    ]);

    $this->actingAs($this->admin, 'sanctum')
        ->deleteJson("/api/v1/academic/time-slots/{$this->timeSlot->id}")
        ->assertStatus(422);
});

test('guru tanpa izin schedules.manage ditolak membuat jam pelajaran', function () {
    $this->actingAs($this->guru1, 'sanctum')
        ->postJson('/api/v1/academic/time-slots', ['name' => 'Jam X', 'start_time' => '10:00', 'end_time' => '10:45', 'order' => 9])
        ->assertForbidden();
});

// ---------- API: jadwal ----------

test('jadwal baru bisa dibuat dengan data yang valid', function () {
    $response = $this->actingAs($this->admin, 'sanctum')
        ->postJson('/api/v1/academic/schedules', [
            'academic_year_id' => $this->activeYearId,
            'semester_id' => $this->semesterId,
            'classroom_id' => $this->classroomA,
            'subject_id' => $this->subject->id,
            'teacher_id' => $this->guru1->id,
            'time_slot_id' => $this->timeSlot->id,
            'day_of_week' => 1,
        ]);

    $response->assertCreated()
        ->assertJsonPath('data.day_name', 'Senin')
        ->assertJsonPath('data.subject.name', 'Matematika')
        ->assertJsonPath('data.teacher.full_name', $this->guru1->full_name);
});

test('daftar jadwal per kelas dan semester', function () {
    Schedule::create([
        'tenant_id' => $this->tenantId,
        'academic_year_id' => $this->activeYearId,
        'semester_id' => $this->semesterId,
        'classroom_id' => $this->classroomA,
        'subject_id' => $this->subject->id,
        'teacher_id' => $this->guru1->id,
        'time_slot_id' => $this->timeSlot->id,
        'day_of_week' => 1,
    ]);

    $response = $this->actingAs($this->admin, 'sanctum')
        ->getJson("/api/v1/academic/schedules?classroom_id={$this->classroomA}&semester_id={$this->semesterId}");

    $response->assertOk();
    expect($response->json('data.data'))->toHaveCount(1);
});

test('daftar jadwal tidak lazy-load profile guru (regresi 500 "Gagal memuat jadwal")', function () {
    // preventLazyLoading (AppServiceProvider::boot(), aktif di luar
    // production) cuma benar-benar mem-flag instance model saat eager-load
    // suatu relasi menghasilkan LEBIH DARI SATU baris (lihat
    // Builder::hydrate(): `if (count($items) > 1) preventsLazyLoading = ...`).
    // Dengan 1 jadwal/1 guru saja test ini tidak akan pernah menangkap bug
    // ini — makanya butuh 2 jadwal dengan 2 guru berbeda, persis kondisi
    // grid jadwal kelas sungguhan yang memicu 500 di server dev (lihat
    // storage/logs: LazyLoadingViolationException saat akses $teacher->profile).
    \Illuminate\Database\Eloquent\Model::preventLazyLoading();

    $timeSlot2 = TimeSlot::create([
        'tenant_id' => $this->tenantId, 'name' => 'Jam 2', 'start_time' => '07:45', 'end_time' => '08:30', 'order' => 2,
    ]);

    Schedule::create([
        'tenant_id' => $this->tenantId,
        'academic_year_id' => $this->activeYearId,
        'semester_id' => $this->semesterId,
        'classroom_id' => $this->classroomA,
        'subject_id' => $this->subject->id,
        'teacher_id' => $this->guru1->id,
        'time_slot_id' => $this->timeSlot->id,
        'day_of_week' => 1,
    ]);
    Schedule::create([
        'tenant_id' => $this->tenantId,
        'academic_year_id' => $this->activeYearId,
        'semester_id' => $this->semesterId,
        'classroom_id' => $this->classroomA,
        'subject_id' => $this->subject->id,
        'teacher_id' => $this->guru2->id,
        'time_slot_id' => $timeSlot2->id,
        'day_of_week' => 1,
    ]);

    $response = $this->actingAs($this->admin, 'sanctum')
        ->getJson("/api/v1/academic/schedules?classroom_id={$this->classroomA}&semester_id={$this->semesterId}");

    $response->assertOk();
    expect($response->json('data.data'))->toHaveCount(2);
    expect($response->json('data.data.0.teacher.full_name'))->not->toBeNull();
    expect($response->json('data.data.1.teacher.full_name'))->not->toBeNull();

    \Illuminate\Database\Eloquent\Model::preventLazyLoading(false);
});

test('kelas bentrok pada hari dan jam yang sama ditolak 422', function () {
    Schedule::create([
        'tenant_id' => $this->tenantId,
        'academic_year_id' => $this->activeYearId,
        'semester_id' => $this->semesterId,
        'classroom_id' => $this->classroomA,
        'subject_id' => $this->subject->id,
        'teacher_id' => $this->guru1->id,
        'time_slot_id' => $this->timeSlot->id,
        'day_of_week' => 1,
    ]);

    $this->actingAs($this->admin, 'sanctum')
        ->postJson('/api/v1/academic/schedules', [
            'academic_year_id' => $this->activeYearId,
            'semester_id' => $this->semesterId,
            'classroom_id' => $this->classroomA,
            'subject_id' => $this->subject->id,
            'teacher_id' => $this->guru2->id,
            'time_slot_id' => $this->timeSlot->id,
            'day_of_week' => 1,
        ])
        ->assertStatus(422);
});

test('guru bentrok mengajar dua kelas pada hari dan jam yang sama ditolak 422', function () {
    Schedule::create([
        'tenant_id' => $this->tenantId,
        'academic_year_id' => $this->activeYearId,
        'semester_id' => $this->semesterId,
        'classroom_id' => $this->classroomA,
        'subject_id' => $this->subject->id,
        'teacher_id' => $this->guru1->id,
        'time_slot_id' => $this->timeSlot->id,
        'day_of_week' => 1,
    ]);

    $this->actingAs($this->admin, 'sanctum')
        ->postJson('/api/v1/academic/schedules', [
            'academic_year_id' => $this->activeYearId,
            'semester_id' => $this->semesterId,
            'classroom_id' => $this->classroomB,
            'subject_id' => $this->subject->id,
            'teacher_id' => $this->guru1->id,
            'time_slot_id' => $this->timeSlot->id,
            'day_of_week' => 1,
        ])
        ->assertStatus(422);
});

test('jadwal bisa diperbarui tanpa memicu konflik dengan dirinya sendiri', function () {
    $schedule = Schedule::create([
        'tenant_id' => $this->tenantId,
        'academic_year_id' => $this->activeYearId,
        'semester_id' => $this->semesterId,
        'classroom_id' => $this->classroomA,
        'subject_id' => $this->subject->id,
        'teacher_id' => $this->guru1->id,
        'time_slot_id' => $this->timeSlot->id,
        'day_of_week' => 1,
    ]);

    $this->actingAs($this->admin, 'sanctum')
        ->putJson("/api/v1/academic/schedules/{$schedule->id}", ['room' => 'Lab Komputer'])
        ->assertOk()
        ->assertJsonPath('data.room', 'Lab Komputer');
});

test('export PDF jadwal mengembalikan file PDF', function () {
    Schedule::create([
        'tenant_id' => $this->tenantId,
        'academic_year_id' => $this->activeYearId,
        'semester_id' => $this->semesterId,
        'classroom_id' => $this->classroomA,
        'subject_id' => $this->subject->id,
        'teacher_id' => $this->guru1->id,
        'time_slot_id' => $this->timeSlot->id,
        'day_of_week' => 1,
    ]);

    $response = $this->actingAs($this->admin, 'sanctum')
        ->get("/api/v1/academic/schedules/export-pdf?classroom_id={$this->classroomA}&semester_id={$this->semesterId}");

    $response->assertOk();
    expect($response->headers->get('Content-Type'))->toBe('application/pdf');
});

test('jadwal bisa dihapus', function () {
    $schedule = Schedule::create([
        'tenant_id' => $this->tenantId,
        'academic_year_id' => $this->activeYearId,
        'semester_id' => $this->semesterId,
        'classroom_id' => $this->classroomA,
        'subject_id' => $this->subject->id,
        'teacher_id' => $this->guru1->id,
        'time_slot_id' => $this->timeSlot->id,
        'day_of_week' => 1,
    ]);

    $this->actingAs($this->admin, 'sanctum')
        ->deleteJson("/api/v1/academic/schedules/{$schedule->id}")
        ->assertOk();

    expect(Schedule::find($schedule->id))->toBeNull();
});

test('setelah hapus, jadwal baru bisa dibuat lagi persis di slot yang sama (partial unique index)', function () {
    // Regresi: `schedule_unique` sebelumnya unique index biasa (bukan
    // partial `WHERE deleted_at IS NULL`), jadi baris yang di-soft-delete
    // tetap dihitung menempati slotnya — insert baru di slot yang sama
    // gagal dengan unique violation mentah dari Postgres. Lihat migrasi
    // 2026_08_09_000001_make_schedule_unique_index_partial.
    $schedule = Schedule::create([
        'tenant_id' => $this->tenantId,
        'academic_year_id' => $this->activeYearId,
        'semester_id' => $this->semesterId,
        'classroom_id' => $this->classroomA,
        'subject_id' => $this->subject->id,
        'teacher_id' => $this->guru1->id,
        'time_slot_id' => $this->timeSlot->id,
        'day_of_week' => 1,
    ]);

    $this->actingAs($this->admin, 'sanctum')
        ->deleteJson("/api/v1/academic/schedules/{$schedule->id}")
        ->assertOk();

    $this->actingAs($this->admin, 'sanctum')
        ->postJson('/api/v1/academic/schedules', [
            'academic_year_id' => $this->activeYearId,
            'semester_id' => $this->semesterId,
            'classroom_id' => $this->classroomA,
            'subject_id' => $this->subject->id,
            'teacher_id' => $this->guru2->id,
            'time_slot_id' => $this->timeSlot->id,
            'day_of_week' => 1,
        ])
        ->assertCreated();
});

test('guru tanpa izin schedules.manage ditolak membuat jadwal', function () {
    $this->actingAs($this->guru1, 'sanctum')
        ->postJson('/api/v1/academic/schedules', [
            'academic_year_id' => $this->activeYearId,
            'semester_id' => $this->semesterId,
            'classroom_id' => $this->classroomA,
            'subject_id' => $this->subject->id,
            'teacher_id' => $this->guru1->id,
            'time_slot_id' => $this->timeSlot->id,
            'day_of_week' => 1,
        ])
        ->assertForbidden();
});

// ---------- endpoint jadwal per kelas (ClassroomController::schedule) ----------

test('endpoint classrooms/{id}/schedule mengembalikan jadwal semester aktif', function () {
    Schedule::create([
        'tenant_id' => $this->tenantId,
        'academic_year_id' => $this->activeYearId,
        'semester_id' => $this->semesterId,
        'classroom_id' => $this->classroomA,
        'subject_id' => $this->subject->id,
        'teacher_id' => $this->guru1->id,
        'time_slot_id' => $this->timeSlot->id,
        'day_of_week' => 1,
    ]);

    $response = $this->actingAs($this->admin, 'sanctum')
        ->getJson("/api/v1/academic/classrooms/{$this->classroomA}/schedule");

    $response->assertOk();
    expect($response->json('data'))->toHaveCount(1);
});

// ---------- salin dari kelas lain ----------

test('salin dari kelas lain menyalin semua jadwal sumber ke kelas tujuan', function () {
    $timeSlot2 = TimeSlot::create([
        'tenant_id' => $this->tenantId, 'name' => 'Jam 2', 'start_time' => '07:45', 'end_time' => '08:30', 'order' => 2,
    ]);
    Schedule::create([
        'tenant_id' => $this->tenantId, 'academic_year_id' => $this->activeYearId, 'semester_id' => $this->semesterId,
        'classroom_id' => $this->classroomA, 'subject_id' => $this->subject->id, 'teacher_id' => $this->guru1->id,
        'time_slot_id' => $this->timeSlot->id, 'day_of_week' => 1,
    ]);
    Schedule::create([
        'tenant_id' => $this->tenantId, 'academic_year_id' => $this->activeYearId, 'semester_id' => $this->semesterId,
        'classroom_id' => $this->classroomA, 'subject_id' => $this->subject->id, 'teacher_id' => $this->guru2->id,
        'time_slot_id' => $timeSlot2->id, 'day_of_week' => 1,
    ]);

    $response = $this->actingAs($this->admin, 'sanctum')
        ->postJson('/api/v1/academic/schedules/copy-from-classroom', [
            'source_classroom_id' => $this->classroomA,
            'source_semester_id' => $this->semesterId,
            'target_classroom_id' => $this->classroomB,
            'target_semester_id' => $this->semesterId,
            'target_academic_year_id' => $this->activeYearId,
            'overwrite' => false,
        ]);

    $response->assertOk()->assertJsonPath('data.copied', 2)->assertJsonPath('data.skipped', []);
    expect(Schedule::where('classroom_id', $this->classroomB)->count())->toBe(2);
});

test('salin dari kelas lain melewati sel yang sudah terisi tanpa overwrite', function () {
    Schedule::create([
        'tenant_id' => $this->tenantId, 'academic_year_id' => $this->activeYearId, 'semester_id' => $this->semesterId,
        'classroom_id' => $this->classroomA, 'subject_id' => $this->subject->id, 'teacher_id' => $this->guru1->id,
        'time_slot_id' => $this->timeSlot->id, 'day_of_week' => 1,
    ]);
    $existing = Schedule::create([
        'tenant_id' => $this->tenantId, 'academic_year_id' => $this->activeYearId, 'semester_id' => $this->semesterId,
        'classroom_id' => $this->classroomB, 'subject_id' => $this->subject->id, 'teacher_id' => $this->guru2->id,
        'time_slot_id' => $this->timeSlot->id, 'day_of_week' => 1,
    ]);

    $response = $this->actingAs($this->admin, 'sanctum')
        ->postJson('/api/v1/academic/schedules/copy-from-classroom', [
            'source_classroom_id' => $this->classroomA,
            'source_semester_id' => $this->semesterId,
            'target_classroom_id' => $this->classroomB,
            'target_semester_id' => $this->semesterId,
            'target_academic_year_id' => $this->activeYearId,
            'overwrite' => false,
        ]);

    $response->assertOk()->assertJsonPath('data.copied', 0);
    expect($response->json('data.skipped'))->toHaveCount(1);
    expect(Schedule::find($existing->id)->teacher_id)->toBe($this->guru2->id);
});

test('salin dari kelas lain menimpa sel yang sudah terisi ketika overwrite aktif', function () {
    Schedule::create([
        'tenant_id' => $this->tenantId, 'academic_year_id' => $this->activeYearId, 'semester_id' => $this->semesterId,
        'classroom_id' => $this->classroomA, 'subject_id' => $this->subject->id, 'teacher_id' => $this->guru1->id,
        'time_slot_id' => $this->timeSlot->id, 'day_of_week' => 1,
    ]);
    Schedule::create([
        'tenant_id' => $this->tenantId, 'academic_year_id' => $this->activeYearId, 'semester_id' => $this->semesterId,
        'classroom_id' => $this->classroomB, 'subject_id' => $this->subject->id, 'teacher_id' => $this->guru2->id,
        'time_slot_id' => $this->timeSlot->id, 'day_of_week' => 1,
    ]);

    $response = $this->actingAs($this->admin, 'sanctum')
        ->postJson('/api/v1/academic/schedules/copy-from-classroom', [
            'source_classroom_id' => $this->classroomA,
            'source_semester_id' => $this->semesterId,
            'target_classroom_id' => $this->classroomB,
            'target_semester_id' => $this->semesterId,
            'target_academic_year_id' => $this->activeYearId,
            'overwrite' => true,
        ]);

    $response->assertOk()->assertJsonPath('data.copied', 1);
    $copied = Schedule::where('classroom_id', $this->classroomB)->first();
    expect($copied->teacher_id)->toBe($this->guru1->id);
});

test('salin dari kelas lain melewati baris yang bentrok guru di kelas lain', function () {
    $classroomC = makeClassroom($this->gradeLevelId, 'X C', $this->activeYearId);

    Schedule::create([
        'tenant_id' => $this->tenantId, 'academic_year_id' => $this->activeYearId, 'semester_id' => $this->semesterId,
        'classroom_id' => $this->classroomA, 'subject_id' => $this->subject->id, 'teacher_id' => $this->guru1->id,
        'time_slot_id' => $this->timeSlot->id, 'day_of_week' => 1,
    ]);
    // guru1 sudah mengajar kelas ketiga (bukan kelas tujuan) di hari & jam yang sama.
    Schedule::create([
        'tenant_id' => $this->tenantId, 'academic_year_id' => $this->activeYearId, 'semester_id' => $this->semesterId,
        'classroom_id' => $classroomC, 'subject_id' => $this->subject->id, 'teacher_id' => $this->guru1->id,
        'time_slot_id' => $this->timeSlot->id, 'day_of_week' => 1,
    ]);

    $response = $this->actingAs($this->admin, 'sanctum')
        ->postJson('/api/v1/academic/schedules/copy-from-classroom', [
            'source_classroom_id' => $this->classroomA,
            'source_semester_id' => $this->semesterId,
            'target_classroom_id' => $this->classroomB,
            'target_semester_id' => $this->semesterId,
            'target_academic_year_id' => $this->activeYearId,
            'overwrite' => false,
        ]);

    $response->assertOk()->assertJsonPath('data.copied', 0);
    expect($response->json('data.skipped.0'))->toContain('Guru sudah memiliki jadwal');
});

test('salin dari kelas lain ditolak 422 kalau kelas sumber belum punya jadwal', function () {
    $this->actingAs($this->admin, 'sanctum')
        ->postJson('/api/v1/academic/schedules/copy-from-classroom', [
            'source_classroom_id' => $this->classroomA,
            'source_semester_id' => $this->semesterId,
            'target_classroom_id' => $this->classroomB,
            'target_semester_id' => $this->semesterId,
            'target_academic_year_id' => $this->activeYearId,
        ])
        ->assertStatus(422);
});

test('salin dari kelas lain ditolak 422 kalau sumber sama dengan tujuan', function () {
    $this->actingAs($this->admin, 'sanctum')
        ->postJson('/api/v1/academic/schedules/copy-from-classroom', [
            'source_classroom_id' => $this->classroomA,
            'source_semester_id' => $this->semesterId,
            'target_classroom_id' => $this->classroomA,
            'target_semester_id' => $this->semesterId,
            'target_academic_year_id' => $this->activeYearId,
        ])
        ->assertStatus(422);
});

test('guru tanpa izin schedules.manage ditolak menyalin dari kelas lain', function () {
    $this->actingAs($this->guru1, 'sanctum')
        ->postJson('/api/v1/academic/schedules/copy-from-classroom', [
            'source_classroom_id' => $this->classroomA,
            'source_semester_id' => $this->semesterId,
            'target_classroom_id' => $this->classroomB,
            'target_semester_id' => $this->semesterId,
            'target_academic_year_id' => $this->activeYearId,
        ])
        ->assertForbidden();
});

// ---------- salin dari hari lain ----------

test('salin dari hari lain menyalin jadwal ke beberapa hari tujuan sekaligus', function () {
    Schedule::create([
        'tenant_id' => $this->tenantId, 'academic_year_id' => $this->activeYearId, 'semester_id' => $this->semesterId,
        'classroom_id' => $this->classroomA, 'subject_id' => $this->subject->id, 'teacher_id' => $this->guru1->id,
        'time_slot_id' => $this->timeSlot->id, 'day_of_week' => 1,
    ]);

    $response = $this->actingAs($this->admin, 'sanctum')
        ->postJson('/api/v1/academic/schedules/copy-from-day', [
            'academic_year_id' => $this->activeYearId,
            'classroom_id' => $this->classroomA,
            'semester_id' => $this->semesterId,
            'source_day_of_week' => 1,
            'target_days' => [3, 5],
            'overwrite' => false,
        ]);

    $response->assertOk()->assertJsonPath('data.copied', 2);
    expect(Schedule::where('classroom_id', $this->classroomA)->count())->toBe(3);
    expect(Schedule::where('classroom_id', $this->classroomA)->where('day_of_week', 3)->exists())->toBeTrue();
    expect(Schedule::where('classroom_id', $this->classroomA)->where('day_of_week', 5)->exists())->toBeTrue();
});

test('salin dari hari lain melewati hari tujuan yang sudah terisi tanpa overwrite', function () {
    Schedule::create([
        'tenant_id' => $this->tenantId, 'academic_year_id' => $this->activeYearId, 'semester_id' => $this->semesterId,
        'classroom_id' => $this->classroomA, 'subject_id' => $this->subject->id, 'teacher_id' => $this->guru1->id,
        'time_slot_id' => $this->timeSlot->id, 'day_of_week' => 1,
    ]);
    Schedule::create([
        'tenant_id' => $this->tenantId, 'academic_year_id' => $this->activeYearId, 'semester_id' => $this->semesterId,
        'classroom_id' => $this->classroomA, 'subject_id' => $this->subject->id, 'teacher_id' => $this->guru2->id,
        'time_slot_id' => $this->timeSlot->id, 'day_of_week' => 3,
    ]);

    $response = $this->actingAs($this->admin, 'sanctum')
        ->postJson('/api/v1/academic/schedules/copy-from-day', [
            'academic_year_id' => $this->activeYearId,
            'classroom_id' => $this->classroomA,
            'semester_id' => $this->semesterId,
            'source_day_of_week' => 1,
            'target_days' => [3],
            'overwrite' => false,
        ]);

    $response->assertOk()->assertJsonPath('data.copied', 0);
    expect($response->json('data.skipped'))->toHaveCount(1);
});

test('salin dari hari lain ditolak 422 kalau hari sumber belum punya jadwal', function () {
    $this->actingAs($this->admin, 'sanctum')
        ->postJson('/api/v1/academic/schedules/copy-from-day', [
            'academic_year_id' => $this->activeYearId,
            'classroom_id' => $this->classroomA,
            'semester_id' => $this->semesterId,
            'source_day_of_week' => 1,
            'target_days' => [2],
        ])
        ->assertStatus(422);
});

test('guru tanpa izin schedules.manage ditolak menyalin dari hari lain', function () {
    Schedule::create([
        'tenant_id' => $this->tenantId, 'academic_year_id' => $this->activeYearId, 'semester_id' => $this->semesterId,
        'classroom_id' => $this->classroomA, 'subject_id' => $this->subject->id, 'teacher_id' => $this->guru1->id,
        'time_slot_id' => $this->timeSlot->id, 'day_of_week' => 1,
    ]);

    $this->actingAs($this->guru1, 'sanctum')
        ->postJson('/api/v1/academic/schedules/copy-from-day', [
            'academic_year_id' => $this->activeYearId,
            'classroom_id' => $this->classroomA,
            'semester_id' => $this->semesterId,
            'source_day_of_week' => 1,
            'target_days' => [2],
        ])
        ->assertForbidden();
});
