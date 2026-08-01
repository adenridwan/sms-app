<?php

namespace App\Services;

use App\Infrastructure\Persistence\Eloquent\Auth\User;
use App\Infrastructure\Persistence\Eloquent\Auth\UserProfile;
use App\Infrastructure\Persistence\Eloquent\Teacher\Teacher;
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
 */
class TeacherRegistrar
{
    /**
     * @param  array<string, mixed>  $data
     * @return array{teacher: Teacher, username: string, password: string}
     */
    public function create(array $data, string $tenantId): array
    {
        $fullName = trim($data['first_name'] . ' ' . ($data['last_name'] ?? ''));
        $username = $this->uniqueUsername($fullName);
        $password = Carbon::parse($data['birth_date'])->format('dmY');

        $teacher = DB::transaction(function () use ($data, $tenantId, $username, $password) {
            $user = User::create([
                'tenant_id' => $tenantId,
                'username' => $username,
                'email' => $data['email'],
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
        ];
    }

    /**
     * Username unik dari nama (slug), ditambah angka bila bentrok.
     * Dicek lintas tenant karena kolom users.username unik global.
     */
    public function uniqueUsername(string $fullName): string
    {
        $base = Str::slug($fullName, '.') ?: 'guru';
        $username = $base;
        $suffix = 1;

        while (User::withoutTenant()->where('username', $username)->exists()) {
            $suffix++;
            $username = "{$base}{$suffix}";
        }

        return $username;
    }
}
