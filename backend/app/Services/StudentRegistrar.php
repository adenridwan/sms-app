<?php

namespace App\Services;

use App\Infrastructure\Persistence\Eloquent\Auth\User;
use App\Infrastructure\Persistence\Eloquent\Auth\UserProfile;
use App\Infrastructure\Persistence\Eloquent\Student\Student;
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
 */
class StudentRegistrar
{
    /**
     * @param  array<string, mixed>  $data
     * @return array{student: Student, username: string, password: string}
     */
    public function create(array $data, string $tenantId): array
    {
        $fullName = trim($data['first_name'] . ' ' . ($data['last_name'] ?? ''));
        $username = $this->uniqueUsername($fullName);
        $password = Carbon::parse($data['birth_date'])->format('dmY');

        $student = DB::transaction(function () use ($data, $tenantId, $username, $password) {
            $user = User::create([
                'tenant_id' => $tenantId,
                'username' => $username,
                'email' => $data['email'],
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
        ];
    }

    /**
     * Username unik dari nama (slug), ditambah angka bila bentrok.
     * Dicek lintas tenant karena kolom users.username unik global.
     */
    public function uniqueUsername(string $fullName): string
    {
        $base = Str::slug($fullName, '.') ?: 'siswa';
        $username = $base;
        $suffix = 1;

        while (User::withoutTenant()->where('username', $username)->exists()) {
            $suffix++;
            $username = "{$base}{$suffix}";
        }

        return $username;
    }
}
