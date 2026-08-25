<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Melengkapi data demo agar aplikasi absensi benar-benar bisa dipakai.
 *
 * Seeder demo yang ada membuat siswa dan guru tanpa kode sama sekali, sehingga
 * seluruh alur yang bertumpu padanya tak pernah bisa dijalankan: pindai QR,
 * ketik Ref ID, dan "Absensi Saya" (yang menampilkan QR milik guru sendiri).
 * Tanpa kode, `ScannerController::findByCode` selalu gagal dan antrean offline
 * tak pernah bisa tersinkron.
 *
 * Idempoten: hanya menyentuh baris yang kodenya masih NULL, jadi aman
 * dijalankan berulang di atas basis yang sudah terisi.
 */
class DemoAttendanceFixtureSeeder extends Seeder
{
    public function run(): void
    {
        $this->command->info('Melengkapi fixture absensi…');

        $students = $this->fill('students', 'STU');
        $teachers = $this->fill('teachers', 'TCH');
        $homeroom = $this->assignHomeroomTeachers();
        $pending = $this->makeSomePaymentsPending();

        $this->command->info("Kode siswa diisi: {$students}");
        $this->command->info("Kode guru diisi: {$teachers}");
        $this->command->info("Kelas diberi wali: {$homeroom}");
        $this->command->info("Pembayaran menunggu verifikasi: {$pending}");
    }

    /**
     * Menunjuk wali kelas untuk kelas yang belum punya.
     *
     * Tanpa ini `teachingClassroomIds()` selalu kosong: tak ada `schedules`
     * dan tak ada `homeroom_teacher_id` di data demo, sehingga setiap guru
     * melihat "Belum ada kelas" dan menu Checklist tak bisa dipakai sama
     * sekali. Pembagiannya bergilir supaya beberapa guru kebagian.
     */
    private function assignHomeroomTeachers(): int
    {
        // Kolomnya menunjuk ke `users`, bukan `teachers` — lihat foreign key
        // `classrooms_homeroom_teacher_id_foreign`.
        $teachers = DB::table('teachers')
            ->whereNotNull('user_id')
            ->orderBy('id')
            ->pluck('user_id')
            ->all();
        if ($teachers === []) {
            return 0;
        }

        $classrooms = DB::table('classrooms')
            ->whereNull('homeroom_teacher_id')
            ->orderBy('id')
            ->pluck('id')
            ->all();

        foreach ($classrooms as $i => $classroomId) {
            DB::table('classrooms')->where('id', $classroomId)->update([
                'homeroom_teacher_id' => $teachers[$i % count($teachers)],
                'updated_at' => now(),
            ]);
        }

        return count($classrooms);
    }

    /**
     * Menyisakan beberapa pembayaran berstatus `pending` agar antrean
     * "Pembayaran untuk Diverifikasi" ada isinya.
     *
     * `DemoFinanceSeeder` menandai semua pembayaran langsung lunas &
     * terverifikasi, sehingga layar verifikasi di aplikasi selalu kosong dan
     * alurnya tak pernah bisa dicoba. Hanya menyentuh basis demo.
     */
    private function makeSomePaymentsPending(int $count = 8): int
    {
        if (DB::table('payments')->where('status', 'pending')->whereNull('verified_at')->exists()) {
            return 0;
        }

        $ids = DB::table('payments')
            ->orderByDesc('created_at')
            ->limit($count)
            ->pluck('id');

        if ($ids->isEmpty()) {
            return 0;
        }

        return DB::table('payments')->whereIn('id', $ids)->update([
            'status' => 'pending',
            'verified_at' => null,
            'verified_by' => null,
            'updated_at' => now(),
        ]);
    }

    /**
     * Beri kode berurutan pada baris yang belum punya.
     *
     * Nomor urut diambil dari jumlah baris yang **sudah** berkode supaya
     * pemanggilan berikutnya melanjutkan, bukan menabrak kode yang ada.
     */
    private function fill(string $table, string $prefix): int
    {
        $next = DB::table($table)->whereNotNull('unique_code')->count() + 1;
        $filled = 0;

        DB::table($table)
            ->whereNull('unique_code')
            ->orderBy('id')
            ->select('id')
            ->chunkById(200, function ($rows) use ($table, $prefix, &$next, &$filled) {
                foreach ($rows as $row) {
                    $code = sprintf('%s-%04d', $prefix, $next);

                    DB::table($table)->where('id', $row->id)->update([
                        'unique_code' => $code,
                        // Kartu RFID memakai kode yang sama supaya satu orang
                        // punya satu identitas, apa pun cara pindainya.
                        'rfid_code' => 'RF-' . $code,
                        'updated_at' => now(),
                    ]);

                    $next++;
                    $filled++;
                }
            });

        return $filled;
    }
}
