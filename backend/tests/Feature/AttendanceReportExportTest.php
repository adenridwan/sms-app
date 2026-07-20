<?php

use Maatwebsite\Excel\Facades\Excel;

/**
 * A3 (ATTENDANCE-PLAN.md) + Fase 4: GET /attendance/reports/pdf memanggil
 * view Blade yang tidak pernah ada, selalu 500. Test ini memastikan PDF
 * benar-benar terunduh, dan menambah cakupan untuk export Excel yang baru.
 */
beforeEach(function () {
    setupSchoolWorld();
    $this->admin = makeUser('admin.laporan', 'admin', 'admin');
    recordAttendanceToday($this->amir, $this->classroomA, 'present');
});

test('download PDF laporan siswa berhasil (dulu selalu 500 karena view hilang)', function () {
    $response = $this->actingAs($this->admin, 'sanctum')->get(
        '/api/v1/attendance/reports/pdf?' . http_build_query([
            'month' => now()->month,
            'year' => now()->year,
            'type' => 'student',
        ])
    );

    $response->assertOk();
    expect($response->headers->get('Content-Type'))->toContain('application/pdf');
});

test('download PDF laporan guru berhasil', function () {
    $response = $this->actingAs($this->admin, 'sanctum')->get(
        '/api/v1/attendance/reports/pdf?' . http_build_query([
            'month' => now()->month,
            'year' => now()->year,
            'type' => 'teacher',
        ])
    );

    $response->assertOk();
    expect($response->headers->get('Content-Type'))->toContain('application/pdf');
});

test('export Excel laporan siswa berisi baris sesuai jumlah siswa', function () {
    Excel::fake();

    $this->actingAs($this->admin, 'sanctum')->get(
        '/api/v1/attendance/reports/excel?' . http_build_query([
            'month' => now()->month,
            'year' => now()->year,
            'classroom_id' => $this->classroomA,
            'type' => 'student',
        ])
    )->assertOk();

    $filename = 'laporan-absensi-student-' . now()->month . '-' . now()->year . '.xlsx';
    $expectedNis = $this->amir->nis;

    Excel::assertDownloaded(
        $filename,
        function (\App\Exports\Attendance\StudentAttendanceMonthlyExport $export) use ($expectedNis) {
            $rows = $export->array();
            expect($rows)->toHaveCount(1)
                ->and($rows[0][0])->toBe($expectedNis);
            return true;
        }
    );
});

test('export Excel laporan guru tidak error walau tanpa data', function () {
    Excel::fake();

    $this->actingAs($this->admin, 'sanctum')->get(
        '/api/v1/attendance/reports/excel?' . http_build_query([
            'month' => now()->month,
            'year' => now()->year,
            'type' => 'teacher',
        ])
    )->assertOk();

    $filename = 'laporan-absensi-teacher-' . now()->month . '-' . now()->year . '.xlsx';

    Excel::assertDownloaded(
        $filename,
        fn (\App\Exports\Attendance\TeacherAttendanceMonthlyExport $export) => true
    );
});
