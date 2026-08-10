<?php

namespace App\Services;

use App\Infrastructure\Persistence\Eloquent\Auth\User;
use App\Infrastructure\Persistence\Eloquent\Auth\UserProfile;
use App\Infrastructure\Persistence\Eloquent\Teacher\Teacher;
use Illuminate\Database\QueryException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Pembuatan guru + akun loginnya (R1, ROLE-ACCESS-PLAN.md): users +
 * user_profiles + teachers dalam satu transaksi.
 *
 * Dipakai bersama oleh form Tambah Guru (TeacherController::store) dan
 * import massal (TeachersImport) supaya aturan akun — username unik,
 * password awal dari tanggal lahir, wajib ganti password saat login
 * pertama — hanya ditulis di satu tempat.
 *
 * `email` boleh dikosongkan: kalau kosong, dibuatkan otomatis dari username
 * (docs/EMAIL-OTOMATIS-AKUN.md). Berbeda dari siswa yang memakai NIS, karena
 * `teachers.nip` nullable sehingga tidak bisa jadi pembeda.
 */
class TeacherRegistrar
{
    /**
     * @param  array<string, mixed>  $data
     * @return array{teacher: Teacher, username: string, password: string, email: string}
     */
    /**
     * uniqueUsername() mengecek dulu baru insert (bukan atomik) — kalau ada
     * proses lain (mis. import guru & siswa yang jalan bersamaan) mengambil
     * username yang sama persis di antara pengecekan dan insert, Postgres
     * menolak dengan unique violation. Daripada itu bikin seluruh baris
     * import gagal dengan pesan SQL mentah, coba lagi beberapa kali dengan
     * username baru — lihat isUsernameConflict().
     */
    private const MAX_USERNAME_ATTEMPTS = 5;

    public function __construct(private EmailGenerator $emailGenerator) {}

    public function create(array $data, string $tenantId): array
    {
        $fullName = trim($data['first_name'] . ' ' . ($data['last_name'] ?? ''));
        $password = Carbon::parse($data['birth_date'])->format('dmY');

        $providedEmail = trim((string) ($data['email'] ?? ''));
        $emailIsGenerated = $providedEmail === '';
        $contactEmail = trim((string) ($data['contact_email'] ?? '')) ?: null;

        // Guru yang mengisi email asli otomatis memakainya juga sebagai alamat
        // surat — notifikasi kehadiran guru memang dikirim ke sana.
        if (! $emailIsGenerated && $contactEmail === null) {
            $contactEmail = $providedEmail;
        }

        for ($attempt = 1; $attempt <= self::MAX_USERNAME_ATTEMPTS; $attempt++) {
            $username = $this->uniqueUsername($fullName);

            // Email guru menumpang username, jadi harus dihitung ulang tiap
            // percobaan — username bisa berubah saat terjadi bentrok.
            $email = $emailIsGenerated
                ? $this->emailGenerator->forTeacher($tenantId, $username)
                : $providedEmail;

            try {
                $teacher = DB::transaction(function () use ($data, $tenantId, $username, $password, $email, $emailIsGenerated, $contactEmail) {
                    $user = User::create([
                        'tenant_id' => $tenantId,
                        'username' => $username,
                        'email' => $email,
                        'contact_email' => $contactEmail,
                        'email_is_generated' => $emailIsGenerated,
                        'password' => Hash::make($password),
                        'status' => 'active',
                        'user_type' => 'teacher',
                    ]);
                    $user->forceFill(['email_verified_at' => now()])->save();
                    $user->assignRole('guru');
                    $user->markPasswordMustChange();

                    UserProfile::create([
                        'user_id' => $user->id,
                        'first_name' => $data['first_name'],
                        'last_name' => $data['last_name'] ?? null,
                        'phone' => $data['phone'] ?? null,
                        'gender' => $data['gender'],
                        'birth_place' => $data['birth_place'] ?? null,
                        'birth_date' => $data['birth_date'],
                        'religion' => $data['religion'] ?? null,
                        'address' => $data['address'] ?? null,
                        'id_number' => $data['id_number'] ?? null,
                    ]);

                    $teacher = Teacher::create([
                        'tenant_id' => $tenantId,
                        'user_id' => $user->id,
                        'nip' => $data['nip'] ?? null,
                        'nuptk' => $data['nuptk'] ?? null,
                        'no_hp' => $data['phone'] ?? null,
                        'join_date' => $data['join_date'] ?? now()->toDateString(),
                        'employment_status' => $data['employment_status'] ?? 'permanent',
                        'status' => $data['status'] ?? 'active',
                        'certification_status' => $data['certification_status'] ?? 'not_certified',
                        'certification_number' => $data['certification_number'] ?? null,
                        'education_level' => $data['education_level'] ?? null,
                        'education_major' => $data['education_major'] ?? null,
                        'university' => $data['university'] ?? null,
                        'teaching_experience_years' => $data['teaching_experience_years'] ?? 0,
                    ]);

                    $teacher->load(['user.profile']);

                    return $teacher;
                });

                return [
                    'teacher' => $teacher,
                    'username' => $username,
                    'password' => $password,
                    'email' => $email,
                ];
            } catch (QueryException $e) {
                if (! $this->isUsernameConflict($e) || $attempt === self::MAX_USERNAME_ATTEMPTS) {
                    throw $e;
                }
            }
        }
    }

    /**
     * Username unik dari nama (slug), ditambah angka bila bentrok.
     * Dicek lintas tenant karena kolom users.username unik global.
     *
     * withTrashed() WAJIB — lihat alasan lengkap di
     * StudentRegistrar::uniqueUsername(): index UNIQUE Postgres ikut
     * menghitung baris yang ter-soft-delete.
     */
    public function uniqueUsername(string $fullName): string
    {
        $base = Str::slug($fullName, '.') ?: 'guru';
        $username = $base;
        $suffix = 1;

        while (User::withoutTenant()->withTrashed()->where('username', $username)->exists()) {
            $suffix++;
            $username = "{$base}{$suffix}";
        }

        return $username;
    }

    private function isUsernameConflict(QueryException $e): bool
    {
        return $e->getCode() === '23505' && str_contains($e->getMessage(), 'users_username_unique');
    }
}
