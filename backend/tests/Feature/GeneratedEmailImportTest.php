<?php

use App\Infrastructure\Persistence\Eloquent\Auth\User;
use App\Infrastructure\Persistence\Eloquent\Student\Student;
use App\Infrastructure\Persistence\Eloquent\System\Setting;
use App\Services\EmailGenerator;
use Illuminate\Http\UploadedFile;

/**
 * Jalur import massal untuk email otomatis — docs/EMAIL-OTOMATIS-AKUN.md.
 *
 * Inti yang dijaga di sini: file impor TIDAK PERNAH bisa menetapkan atau
 * menimpa email login siswa, karena itu kredensial — menimpanya dari file akan
 * mengunci siswa tanpa ia tahu.
 */
beforeEach(function () {
    setupSchoolWorld();
    $this->admin = makeUser('admin.imporemail', 'admin', 'admin');

    Setting::setForTenant(
        $this->tenantId,
        EmailGenerator::SETTING_GROUP,
        EmailGenerator::SETTING_KEY,
        'almuawanah.school',
    );
});

/** File CSV siswa dengan kolom sesuai template terbaru (tanpa kolom email login). */
function studentCsv(array $rows): UploadedFile
{
    $lines = ['nis,nama_depan,nama_belakang,email_kontak,jenis_kelamin,tanggal_lahir'];

    foreach ($rows as $row) {
        $lines[] = implode(',', $row);
    }

    $path = tempnam(sys_get_temp_dir(), 'siswa') . '.csv';
    file_put_contents($path, implode("\n", $lines));

    return new UploadedFile($path, 'siswa.csv', 'text/csv', null, true);
}

function importStudents(UploadedFile $file)
{
    return test()->actingAs(test()->admin, 'sanctum')
        ->postJson('/api/v1/students/import', ['file' => $file]);
}

test('import tanpa kolom email tetap membuat akun dengan email otomatis', function () {
    $response = importStudents(studentCsv([
        ['2024001', 'Ahmad Ridwan', 'Hidayat', 'orangtua@gmail.com', 'L', '2012-07-14'],
        ['2024002', 'Dewi', 'Lestari', '', 'P', '2012-11-02'],
    ]));

    $response->assertOk()->assertJsonPath('data.created', 2);

    $ahmad = Student::where('nis', '2024001')->first()->user;
    $dewi = Student::where('nis', '2024002')->first()->user;

    expect($ahmad->email)->toBe('ahmad.2024001@almuawanah.school')
        ->and($ahmad->contact_email)->toBe('orangtua@gmail.com')
        ->and($ahmad->email_is_generated)->toBeTrue()
        ->and($dewi->email)->toBe('dewi.2024002@almuawanah.school')
        ->and($dewi->contact_email)->toBeNull();
});

test('satu email orang tua untuk beberapa anak lolos import', function () {
    importStudents(studentCsv([
        ['2024010', 'Ahmad', '', 'orangtua@gmail.com', 'L', '2012-07-14'],
        ['2024011', 'Fatimah', '', 'orangtua@gmail.com', 'P', '2013-02-01'],
        ['2024012', 'Zaid', '', 'orangtua@gmail.com', 'L', '2014-05-20'],
    ]))->assertOk()->assertJsonPath('data.created', 3);

    expect(User::withoutTenant()->where('contact_email', 'orangtua@gmail.com')->count())->toBe(3);
});

test('re-import tidak mengubah email login siswa yang sudah ada', function () {
    importStudents(studentCsv([
        ['2024020', 'Ahmad', '', 'lama@gmail.com', 'L', '2012-07-14'],
    ]))->assertOk();

    $emailAwal = Student::where('nis', '2024020')->first()->user->email;

    // Nama berubah di file — email login TIDAK boleh ikut berubah.
    importStudents(studentCsv([
        ['2024020', 'Ahmad Baru', '', 'baru@gmail.com', 'L', '2012-07-14'],
    ]))->assertOk()->assertJsonPath('data.updated', 1);

    $user = Student::where('nis', '2024020')->first()->user->fresh();

    expect($user->email)->toBe($emailAwal)
        ->and($user->contact_email)->toBe('baru@gmail.com');
});

test('email kontak yang dikosongkan saat update tidak menghapus yang lama', function () {
    importStudents(studentCsv([
        ['2024030', 'Ahmad', '', 'orangtua@gmail.com', 'L', '2012-07-14'],
    ]))->assertOk();

    importStudents(studentCsv([
        ['2024030', 'Ahmad', '', '', 'L', '2012-07-14'],
    ]))->assertOk();

    expect(Student::where('nis', '2024030')->first()->user->contact_email)
        ->toBe('orangtua@gmail.com');
});

test('kolom email pada file hasil export diabaikan, bukan jadi email kontak', function () {
    // File hasil export memuat kolom `email` berisi alamat login sintetis.
    // Kalau ia dibaca sebagai email_kontak, contact_email seluruh siswa akan
    // terisi alamat yang tidak menerima surat apa pun.
    $path = tempnam(sys_get_temp_dir(), 'siswa') . '.csv';
    file_put_contents($path, implode("\n", [
        'nis,nama_depan,nama_belakang,email,email_kontak,jenis_kelamin,tanggal_lahir',
        '2024040,Ahmad,,ahmad.2024040@almuawanah.school,,L,2012-07-14',
    ]));

    importStudents(new UploadedFile($path, 'siswa.csv', 'text/csv', null, true))->assertOk();

    expect(Student::where('nis', '2024040')->first()->user->contact_email)->toBeNull();
});

test('format email kontak yang salah dilaporkan per baris', function () {
    $response = importStudents(studentCsv([
        ['2024050', 'Ahmad', '', 'bukan-email', 'L', '2012-07-14'],
    ]));

    $response->assertOk()->assertJsonPath('data.created', 0);
    expect($response->json('data.errors.0'))->toContain('email_kontak');
});

test('import ditolak sekali saja bila domain sekolah belum diatur', function () {
    Setting::setForTenant($this->tenantId, EmailGenerator::SETTING_GROUP, EmailGenerator::SETTING_KEY, null);

    $response = importStudents(studentCsv([
        ['2024060', 'Ahmad', '', '', 'L', '2012-07-14'],
        ['2024061', 'Dewi', '', '', 'P', '2012-11-02'],
    ]));

    $response->assertOk()->assertJsonPath('data.created', 0);

    // Satu pesan yang mengarahkan admin — bukan satu error per baris.
    expect($response->json('data.errors'))->toBe([EmailGenerator::domainMissingMessage()]);
});

test('import guru boleh mengosongkan email dan tetap dapat akun', function () {
    $path = tempnam(sys_get_temp_dir(), 'guru') . '.csv';
    file_put_contents($path, implode("\n", [
        'nama_depan,nama_belakang,email,email_kontak,no_hp,nip,jenis_kelamin,tanggal_lahir',
        'Budi,Santoso,,budi.asli@gmail.com,081234567890,198501012010011001,L,1985-01-01',
    ]));

    $this->actingAs($this->admin, 'sanctum')
        ->postJson('/api/v1/teachers/import', [
            'file' => new UploadedFile($path, 'guru.csv', 'text/csv', null, true),
        ])->assertOk()->assertJsonPath('data.created', 1);

    $user = User::withoutTenant()->where('username', 'budi.santoso')->first();

    expect($user->email)->toBe('budi.santoso@almuawanah.school')
        ->and($user->contact_email)->toBe('budi.asli@gmail.com')
        ->and($user->email_is_generated)->toBeTrue();
});

/**
 * Regresi produksi 2026-08-09: `students` unik pada (tenant_id, nis) dan index
 * itu ikut menghitung baris ter-soft-delete, jadi siswa yang pernah dihapus
 * lalu diimpor ulang menabrak "students_tenant_id_nis_unique" sebagai error
 * SQL mentah. Sekarang barisnya dipulihkan — NIS sama berarti orang yang sama.
 */
test('siswa yang sudah dihapus dipulihkan saat diimpor ulang, bukan menabrak unique', function () {
    importStudents(studentCsv([
        ['25260024', 'Apeva', 'Affshen Meysya', '', 'P', '2012-07-14'],
    ]))->assertOk();

    $siswa = Student::where('nis', '25260024')->first();
    $siswaId = $siswa->id;
    $emailAwal = $siswa->user->email;

    $this->actingAs($this->admin, 'sanctum')
        ->deleteJson("/api/v1/students/{$siswaId}")->assertOk();

    $response = importStudents(studentCsv([
        ['25260024', 'Apeva', 'Affshen Meysya', 'orangtua@gmail.com', 'P', '2012-07-14'],
    ]));

    $response->assertOk()
        ->assertJsonPath('data.errors', [])
        ->assertJsonPath('data.updated', 1)
        ->assertJsonPath('data.created', 0);

    $dipulihkan = Student::where('nis', '25260024')->first();

    // Baris yang sama dipulihkan, bukan siswa kembar dengan NIS sama.
    expect($dipulihkan->id)->toBe($siswaId)
        ->and($dipulihkan->user->email)->toBe($emailAwal)
        ->and($dipulihkan->user->contact_email)->toBe('orangtua@gmail.com')
        ->and(Student::withTrashed()->where('nis', '25260024')->count())->toBe(1);
});

test('nama sama dengan siswa yang sudah dihapus tetap dapat username baru', function () {
    importStudents(studentCsv([
        ['25260024', 'Apeva', 'Affshen Meysya', '', 'P', '2012-07-14'],
    ]))->assertOk();

    $lama = Student::where('nis', '25260024')->first();
    $usernameLama = $lama->user->username;

    $this->actingAs($this->admin, 'sanctum')
        ->deleteJson("/api/v1/students/{$lama->id}")->assertOk();

    // Nama identik tapi NIS berbeda = siswa lain; username harus dibuat baru.
    $response = importStudents(studentCsv([
        ['25260099', 'Apeva', 'Affshen Meysya', '', 'P', '2013-01-05'],
    ]));

    $response->assertOk()->assertJsonPath('data.errors', [])->assertJsonPath('data.created', 1);

    expect(Student::where('nis', '25260099')->first()->user->username)->not->toBe($usernameLama);
});

// ── Kolom `kelas`: nama maupun kode ──────────────────────────────────────────

/** Varian CSV yang menyertakan kolom `kelas`. */
function studentCsvWithClass(array $rows): UploadedFile
{
    $lines = ['nis,nama_depan,nama_belakang,email_kontak,jenis_kelamin,tanggal_lahir,kelas'];

    foreach ($rows as $row) {
        $lines[] = implode(',', $row);
    }

    $path = tempnam(sys_get_temp_dir(), 'siswa') . '.csv';
    file_put_contents($path, implode("\n", $lines));

    return new UploadedFile($path, 'siswa.csv', 'text/csv', null, true);
}

/**
 * Regresi 2026-08-09: kolom `kelas` dulu hanya dicocokkan ke `classrooms.code`,
 * padahal kode dibuat otomatis (KLS01) dan tidak pernah ditampilkan di menu
 * Kelas — admin mustahil menebaknya, jadi penempatan kelas tak pernah berhasil.
 */
test('kolom kelas menerima nama kelas yang terlihat di menu Kelas', function () {
    // Helper test membuat kelas bernama "X A" dengan kode "x-a".
    importStudents(studentCsvWithClass([
        ['2024200', 'Ahmad', '', '', 'L', '2012-07-14', 'X A'],
    ]))->assertOk()->assertJsonPath('data.errors', []);

    expect(Student::where('nis', '2024200')->first()->currentClass?->name)->toBe('X A');
});

test('kolom kelas tetap menerima kode kelas', function () {
    importStudents(studentCsvWithClass([
        ['2024201', 'Dewi', '', '', 'P', '2012-11-02', 'x-a'],
    ]))->assertOk()->assertJsonPath('data.errors', []);

    expect(Student::where('nis', '2024201')->first()->currentClass?->name)->toBe('X A');
});

test('nama kelas tidak peduli besar kecil huruf', function () {
    importStudents(studentCsvWithClass([
        ['2024202', 'Budi', '', '', 'L', '2012-11-02', 'x a'],
    ]))->assertOk()->assertJsonPath('data.errors', []);

    expect(Student::where('nis', '2024202')->first()->currentClass?->name)->toBe('X A');
});

test('kelas yang tidak ada menyebutkan pilihan yang tersedia', function () {
    $response = importStudents(studentCsvWithClass([
        ['2024203', 'Siti', '', '', 'P', '2012-11-02', 'XII IPA 9'],
    ]));

    $response->assertOk()->assertJsonPath('data.created', 1);

    // Siswanya tetap masuk, hanya penempatan kelasnya yang dilewati — dan
    // pesannya menyebut kelas apa saja yang sah supaya bisa langsung dibetulkan.
    expect($response->json('data.errors.0'))->toContain('X A')
        ->and(Student::where('nis', '2024203')->first()->currentClass)->toBeNull();
});
