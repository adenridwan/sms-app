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
