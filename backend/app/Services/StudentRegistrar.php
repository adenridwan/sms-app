<?php

namespace App\Services;

use App\Infrastructure\Persistence\Eloquent\Auth\User;
use App\Infrastructure\Persistence\Eloquent\Auth\UserProfile;
use App\Infrastructure\Persistence\Eloquent\Student\Student;
use Illuminate\Database\QueryException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Pembuatan siswa + akun loginnya: users + user_profiles + students dalam
 * satu transaksi.
 *
 * Aturan akunnya sengaja disamakan dengan guru (lihat TeacherRegistrar):
 * password awal = tanggal lahir ddmmyyyy dan wajib diganti saat login
 * pertama. Jalur form Tambah Siswa (StudentController::store) masih memakai
 * aturan lamanya sendiri — lihat catatan di sana.
 *
 * `email` boleh dikosongkan: kalau kosong, dibuatkan otomatis oleh
 * EmailGenerator dari nama depan + NIS (docs/EMAIL-OTOMATIS-AKUN.md).
 */
class StudentRegistrar
{
    /**
     * @param  array<string, mixed>  $data
     * @return array{student: Student, username: string, password: string, email: string}
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

        // Email kosong → dibuatkan otomatis dari nama depan + NIS. Alamat ini
        // identitas login, bukan alamat surat; surat dikirim ke contact_email.
        // Lihat docs/EMAIL-OTOMATIS-AKUN.md.
        $email = $this->text($data['email'] ?? null);
        $emailIsGenerated = $email === '';

        if ($emailIsGenerated) {
            $email = $this->emailGenerator->forStudent($tenantId, $data['first_name'], (string) $data['nis']);
        }

        $contactEmail = $this->text($data['contact_email'] ?? null) ?: null;

        for ($attempt = 1; $attempt <= self::MAX_USERNAME_ATTEMPTS; $attempt++) {
            $username = $this->uniqueUsername($fullName);

            try {
                $student = DB::transaction(function () use ($data, $tenantId, $username, $password, $email, $emailIsGenerated, $contactEmail) {
                    $user = User::create([
                        'tenant_id' => $tenantId,
                        'username' => $username,
                        'email' => $email,
                        'contact_email' => $contactEmail,
                        'email_is_generated' => $emailIsGenerated,
                        'password' => Hash::make($password),
                        'status' => 'active',
                        'user_type' => 'student',
                    ]);
                    $user->forceFill(['email_verified_at' => now()])->save();
                    $user->assignRole('siswa');
                    $user->markPasswordMustChange();

                    UserProfile::create([
                        'user_id' => $user->id,
                        'first_name' => $data['first_name'],
                        'last_name' => $data['last_name'] ?? null,
                        'phone' => $data['phone'] ?? null,
                        'gender' => $data['gender'],
                        'birth_place' => $data['birth_place'] ?? null,
                        'birth_date' => $data['birth_date'],
                        'address' => $data['address'] ?? null,
                        'id_number' => $data['id_number'] ?? null,
                    ]);

                    $student = Student::create([
                        'tenant_id' => $tenantId,
                        'user_id' => $user->id,
                        'nis' => $data['nis'],
                        'nisn' => $data['nisn'] ?? null,
                        'previous_school' => $data['previous_school'] ?? null,
                        // Tahun masuk sengaja tidak dipakai (konsisten dengan
                        // StudentController::store yang juga mengabaikannya).
                        'entry_date' => now()->toDateString(),
                        'entry_type' => 'new',
                        'status' => 'active',
                    ]);

                    $student->load(['user.profile']);

                    return $student;
                });

                return [
                    'student' => $student,
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
     * withTrashed() WAJIB: index UNIQUE Postgres ikut menghitung baris yang
     * ter-soft-delete, sedangkan global scope SoftDeletes menyembunyikannya
     * dari query biasa. Tanpa ini, username milik siswa yang pernah dihapus
     * dianggap bebas, lalu insert-nya ditolak "users_username_unique" — dan
     * karena nama yang dihasilkan selalu sama, kelima percobaan ulang di
     * create() gagal semua.
     */
    public function uniqueUsername(string $fullName): string
    {
        $base = Str::slug($fullName, '.') ?: 'siswa';
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

    private function text(mixed $value): string
    {
        return trim((string) ($value ?? ''));
    }
}
