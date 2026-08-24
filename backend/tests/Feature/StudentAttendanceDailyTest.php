<?php

/**
 * Regresi: endpoint GET /attendance/students/daily dipakai halaman Absensi Siswa
 * untuk menampilkan daftar siswa begitu kelas dipilih. Sempat eager-load
 * `student.user` saja padahal `User::full_name` dirakit dari relasi `profile`
 * (accessor, bukan kolom) — di lingkungan non-produksi lazy loading dilarang
 * (AppServiceProvider::shouldBeStrict), jadi endpoint 500 dan halaman tampak
 * kosong walau kelasnya punya siswa aktif. Pola bug yang sama pernah terjadi
 * di LoginLogService (lihat CLAUDE.md).
 */
beforeEach(function () {
    // Dipatok ke jam pagi (sebelum check_out_start default 14:00) supaya
    // status "belum_scan" tidak berubah jadi "alfa" tergantung jam
    // sungguhan saat suite ini dijalankan (AttendanceStatusResolver
    // membandingkan now() terhadap jam pulang standar untuk tanggal "hari
    // ini" tanpa record). Tanggalnya tetap hari nyata — hanya jamnya dipatok.
    \Illuminate\Support\Carbon::setTestNow(\Illuminate\Support\Carbon::now()->setTime(8, 0));

    setupSchoolWorld();
    $this->admin = makeUser('admin.absensi.daily', 'admin', 'admin');
});

afterEach(function () {
    \Illuminate\Support\Carbon::setTestNow();
});

test('daily mengembalikan daftar siswa aktif di kelas beserta nama lengkapnya', function () {
    $response = $this->actingAs($this->admin, 'sanctum')
        ->getJson('/api/v1/attendance/students/daily?' . http_build_query([
            'classroom_id' => $this->classroomA,
            'date' => now()->toDateString(),
        ]))
        ->assertOk();

    $students = $response->json('data.students');

    expect($students)->not->toBeEmpty();

    $amirRow = collect($students)->firstWhere('student_id', $this->amir->id);
    expect($amirRow)->not->toBeNull();
    expect($amirRow['name'])->not->toBeNull();
    expect($amirRow['nis'])->toBe($this->amir->nis);
    expect($amirRow['status'])->toBe('belum_scan');
});

/**
 * Regresi: calculateSummary() dulu menghitung ulang dari slug wire
 * ('hadir'/'sakit'/dst) memakai AttendanceStatus::summaryFromRaw(), padahal
 * fungsi itu mengharapkan key nilai DB Inggris ('present'/'sick'/dst).
 * Akibatnya card ringkasan Hadir/Sakit/Izin/Alfa selalu 0 walau absensi
 * sudah berhasil disimpan — hanya "Belum Scan" yang pernah benar.
 */
test('summary pada daily terisi benar setelah absensi disimpan lewat storeBulk', function () {
    [$budi] = makeStudent('budi.absensi.daily', $this->classroomA);
    [$citra] = makeStudent('citra.absensi.daily', $this->classroomA);

    $this->actingAs($this->admin, 'sanctum')
        ->postJson('/api/v1/attendance/students/bulk', [
            'classroom_id' => $this->classroomA,
            'date' => now()->toDateString(),
            'attendances' => [
                ['student_id' => $this->amir->id, 'status' => 'hadir'],
                ['student_id' => $budi->id, 'status' => 'sakit'],
                ['student_id' => $citra->id, 'status' => 'tanpa_keterangan'],
            ],
        ])
        ->assertOk();

    $response = $this->actingAs($this->admin, 'sanctum')
        ->getJson('/api/v1/attendance/students/daily?' . http_build_query([
            'classroom_id' => $this->classroomA,
            'date' => now()->toDateString(),
        ]))
        ->assertOk();

    $summary = $response->json('data.summary');

    expect($summary['hadir'])->toBe(1);
    expect($summary['sakit'])->toBe(1);
    expect($summary['izin'])->toBe(0);
    // tanpa_keterangan digabung ke bucket 'alfa', sama seperti perilaku
    // summaryFromRaw() untuk laporan lain (absent+alpha -> alfa).
    expect($summary['alfa'])->toBe(1);
    expect($summary['belum_scan'])->toBe(0);
    expect($summary['total'])->toBe(3);
});

/**
 * storeBulk dulu tidak pernah menulis check_in_time/menit_keterlambatan sama
 * sekali — menandai siswa Hadir lewat dropdown status di halaman Absensi
 * Siswa tidak pernah mengisi jam masuk. Sekarang: tanpa check_in_time
 * eksplisit, jam masuk diambil dari jam masuk resmi (master Pengaturan
 * Absensi) sehingga otomatis tepat waktu (menit_keterlambatan = 0).
 */
test('storeBulk mengisi jam masuk dari pengaturan absensi kalau tidak diisi manual', function () {
    $this->actingAs($this->admin, 'sanctum')
        ->postJson('/api/v1/attendance/students/bulk', [
            'classroom_id' => $this->classroomA,
            'date' => now()->toDateString(),
            'attendances' => [
                ['student_id' => $this->amir->id, 'status' => 'hadir'],
            ],
        ])
        ->assertOk();

    $attendance = App\Infrastructure\Persistence\Eloquent\Attendance\StudentAttendance::where('student_id', $this->amir->id)->first();

    expect($attendance->check_in_time->format('H:i'))->toBe('06:00');
    expect($attendance->menit_keterlambatan)->toBe(0);
});

test('storeBulk menghitung menit_keterlambatan ketika jam masuk manual melewati batas toleransi', function () {
    $this->actingAs($this->admin, 'sanctum')
        ->postJson('/api/v1/attendance/students/bulk', [
            'classroom_id' => $this->classroomA,
            'date' => now()->toDateString(),
            'attendances' => [
                // Default pengaturan: check_in_end 07:30 + toleransi 15 menit = batas 07:45.
                ['student_id' => $this->amir->id, 'status' => 'hadir', 'check_in_time' => '08:00'],
            ],
        ])
        ->assertOk();

    $attendance = App\Infrastructure\Persistence\Eloquent\Attendance\StudentAttendance::where('student_id', $this->amir->id)->first();

    expect($attendance->check_in_time->format('H:i'))->toBe('08:00');
    expect($attendance->menit_keterlambatan)->toBe(15);
});

test('storeBulk menolak jam masuk manual yang kurang dari jam masuk resmi', function () {
    $this->actingAs($this->admin, 'sanctum')
        ->postJson('/api/v1/attendance/students/bulk', [
            'classroom_id' => $this->classroomA,
            'date' => now()->toDateString(),
            'attendances' => [
                // Default pengaturan: jam masuk resmi 06:00.
                ['student_id' => $this->amir->id, 'status' => 'hadir', 'check_in_time' => '05:30'],
            ],
        ])
        ->assertStatus(422);

    expect(App\Infrastructure\Persistence\Eloquent\Attendance\StudentAttendance::where('student_id', $this->amir->id)->exists())->toBeFalse();
});
