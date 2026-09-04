<?php

namespace App\Services;

use App\Infrastructure\Persistence\Eloquent\Auth\User;
use App\Infrastructure\Persistence\Eloquent\Auth\UserProfile;
use App\Infrastructure\Persistence\Eloquent\Staff\Staff;
use Illuminate\Database\QueryException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Pembuatan staf + akun loginnya: users + user_profiles + staff dalam
 * satu transaksi. Mirip dengan TeacherRegistrar tetapi untuk staf
 * non-teaching.
 */
class StaffRegistrar
{
    private const MAX_USERNAME_ATTEMPTS = 5;

    public function __construct(private EmailGenerator $emailGenerator) {}

    /**
     * @param  array<string, mixed>  $data
     * @return array{staff: Staff, username: string, password: string, email: string}
     */
    public function create(array $data, string $tenantId): array
    {
        $fullName = trim($data['first_name'] . ' ' . ($data['last_name'] ?? ''));
        $password = Carbon::parse($data['birth_date'])->format('dmY');

        $providedEmail = trim((string) ($data['email'] ?? ''));
        $emailIsGenerated = $providedEmail === '';
        $contactEmail = trim((string) ($data['contact_email'] ?? '')) ?: null;

        // Staf yang mengisi email asli otomatis memakainya juga sebagai alamat surat
        if (! $emailIsGenerated && $contactEmail === null) {
            $contactEmail = $providedEmail;
        }

        for ($attempt = 1; $attempt <= self::MAX_USERNAME_ATTEMPTS; $attempt++) {
            $username = $this->uniqueUsername($fullName);

            $email = $emailIsGenerated
                ? $this->emailGenerator->forStaff($tenantId, $username)
                : $providedEmail;

            try {
                $staff = DB::transaction(function () use ($data, $tenantId, $username, $password, $email, $emailIsGenerated, $contactEmail) {
                    $user = User::create([
                        'tenant_id' => $tenantId,
                        'username' => $username,
                        'email' => $email,
                        'contact_email' => $contactEmail,
                        'email_is_generated' => $emailIsGenerated,
                        'password' => Hash::make($password),
                        'status' => 'active',
                        'user_type' => 'staff',
                    ]);
                    $user->forceFill(['email_verified_at' => now()])->save();
                    $user->assignRole('staf');
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

                    $staff = Staff::create([
                        'tenant_id' => $tenantId,
                        'user_id' => $user->id,
                        'employee_id' => $data['employee_id'] ?? null,
                        'department_id' => $data['department_id'] ?? null,
                        'position_id' => $data['position_id'] ?? null,
                        'no_hp' => $data['phone'] ?? null,
                        'join_date' => $data['join_date'] ?? now()->toDateString(),
                        'employment_status' => $data['employment_status'] ?? 'permanent',
                        'education_level' => $data['education_level'] ?? null,
                        'status' => $data['status'] ?? 'active',
                    ]);

                    $staff->load(['user.profile', 'department', 'position']);

                    return $staff;
                });

                return [
                    'staff' => $staff,
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
     */
    public function uniqueUsername(string $fullName): string
    {
        $base = Str::slug($fullName, '.') ?: 'staf';
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
