<?php

use App\Infrastructure\Persistence\Eloquent\Attendance\NotificationSetting;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

/**
 * Fase 3 ATTENDANCE-PLAN.md §5 — endpoint batch "Kirim Notifikasi" rekap
 * harian per kelas. QUEUE_CONNECTION=sync di phpunit.xml sehingga job
 * SendClassAttendanceRecap berjalan inline dan Http::fake bisa mengamati
 * panggilan provider Fonnte.
 */
beforeEach(function () {
    setupSchoolWorld();
    $this->admin = makeUser('admin.notif', 'admin', 'admin');
});

function enableWhatsApp(): void
{
    NotificationSetting::getForTenant(test()->tenantId)->update([
        'wa_enabled' => true,
        'wa_provider' => 'fonnte',
        'wa_api_key' => 'test-key',
    ]);
}

function addPrimaryGuardian(string $studentId, string $phone): void
{
    DB::table('student_guardians')->insert([
        'id' => Str::uuid()->toString(),
        'tenant_id' => test()->tenantId,
        'student_id' => $studentId,
        'relationship' => 'father',
        'name' => 'Wali Uji',
        'phone' => $phone,
        'is_primary_contact' => true,
        'created_at' => now(),
        'updated_at' => now(),
    ]);
}

test('rekap hadir terkirim via WA ke nomor wali', function () {
    enableWhatsApp();
    addPrimaryGuardian($this->amir->id, '081234567890');
    recordAttendanceToday($this->amir, $this->classroomA, 'present');
    Http::fake(['api.fonnte.com/*' => Http::response(['status' => true])]);

    $this->actingAs($this->admin, 'sanctum')
        ->postJson('/api/v1/attendance/students/notify-daily', [
            'classroom_id' => $this->classroomA,
            'date' => now()->toDateString(),
        ])
        ->assertOk()
        ->assertJsonPath('data.recipients', 1);

    Http::assertSent(function ($request) {
        return str_contains($request->url(), 'api.fonnte.com')
            && $request['target'] === '6281234567890'
            && str_contains($request['message'], 'hadir');
    });
});

test('tanpa kanal notifikasi terkonfigurasi ditolak 422', function () {
    $this->actingAs($this->admin, 'sanctum')
        ->postJson('/api/v1/attendance/students/notify-daily', [
            'classroom_id' => $this->classroomA,
            'date' => now()->toDateString(),
        ])
        ->assertStatus(422);
});

test('guru non-pengampu ditolak 403', function () {
    enableWhatsApp();
    $guru = makeUser('guru.notif', 'guru', 'teacher');
    assignClassroom($guru, $this->classroomB);

    $this->actingAs($guru, 'sanctum')
        ->postJson('/api/v1/attendance/students/notify-daily', [
            'classroom_id' => $this->classroomA,
            'date' => now()->toDateString(),
        ])
        ->assertForbidden();
});

test('siswa alfa (tanpa baris, tanggal lampau) menerima pesan absent', function () {
    enableWhatsApp();
    addPrimaryGuardian($this->amir->id, '081234567890');
    Http::fake(['api.fonnte.com/*' => Http::response(['status' => true])]);

    $this->actingAs($this->admin, 'sanctum')
        ->postJson('/api/v1/attendance/students/notify-daily', [
            'classroom_id' => $this->classroomA,
            'date' => now()->subDay()->toDateString(),
        ])
        ->assertOk();

    Http::assertSent(function ($request) {
        return str_contains($request['message'], 'tidak hadir');
    });
});

test('siswa sakit dilewati (sudah dinotifikasi saat approval izin)', function () {
    enableWhatsApp();
    addPrimaryGuardian($this->amir->id, '081234567890');
    recordAttendanceToday($this->amir, $this->classroomA, 'sick');
    Http::fake(['api.fonnte.com/*' => Http::response(['status' => true])]);

    $this->actingAs($this->admin, 'sanctum')
        ->postJson('/api/v1/attendance/students/notify-daily', [
            'classroom_id' => $this->classroomA,
            'date' => now()->toDateString(),
        ])
        ->assertOk();

    Http::assertNothingSent();
});

test('flag notify_absent=false membuat pesan alfa tidak dikirim', function () {
    enableWhatsApp();
    NotificationSetting::getForTenant($this->tenantId)->update(['notify_absent' => false]);
    addPrimaryGuardian($this->amir->id, '081234567890');
    Http::fake(['api.fonnte.com/*' => Http::response(['status' => true])]);

    $this->actingAs($this->admin, 'sanctum')
        ->postJson('/api/v1/attendance/students/notify-daily', [
            'classroom_id' => $this->classroomA,
            'date' => now()->subDay()->toDateString(),
        ])
        ->assertOk();

    Http::assertNothingSent();
});
