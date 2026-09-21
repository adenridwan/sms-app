<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Api\ApiController;
use App\Http\Resources\UserResource;
use App\Infrastructure\Persistence\Eloquent\Auth\User;
use App\Infrastructure\Persistence\Eloquent\Auth\UserProfile;
use App\Infrastructure\Persistence\Eloquent\Staff\Staff;
use App\Infrastructure\Persistence\Eloquent\Student\Student;
use App\Infrastructure\Persistence\Eloquent\Student\StudentGuardian;
use App\Infrastructure\Persistence\Eloquent\Teacher\Teacher;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;

class UserController extends ApiController
{
    /**
     * Display a paginated listing of users.
     */
    public function index(Request $request): JsonResponse
    {
        $query = User::with(['roles', 'profile'])
            ->when($request->search, function ($q, $search) {
                // Join profile sekali saja, lebih cepat dari whereHas berulang.
                // Mendukung pencarian nama lengkap (first + last) dan email.
                $q->leftJoin('user_profiles as up', 'users.id', '=', 'up.user_id')
                    ->where(function ($query) use ($search) {
                        $term = '%' . $search . '%';
                        $query->where('users.username', 'ilike', $term)
                            ->orWhere('users.email', 'ilike', $term)
                            ->orWhere('up.first_name', 'ilike', $term)
                            ->orWhere('up.last_name', 'ilike', $term)
                            // Gabungan nama lengkap agar "Budi Santoso" ketemu
                            ->orWhereRaw("concat(up.first_name, ' ', up.last_name) ilike ?", [$term]);
                    })
                    ->select('users.*'); // Hindari kolom duplikat dari join
            })
            ->when($request->role, fn ($q, $role) => $q->role($role))
            ->when($request->status, fn ($q, $status) => $q->where('status', $status))
            ->when($request->user_type, fn ($q, $type) => $q->where('user_type', $type));

        $sortField = in_array($request->get('sort'), ['username', 'email', 'created_at', 'last_login_at'], true)
            ? $request->get('sort')
            : 'created_at';
        $sortDirection = $request->get('direction') === 'asc' ? 'asc' : 'desc';
        $query->orderBy($sortField, $sortDirection);

        $perPage = min((int) $request->get('per_page', 15), 100);
        $users = $query->paginate($perPage);

        return $this->success(UserResource::collection($users)->response()->getData(true));
    }

    /**
     * Store a newly created user with profile and role.
     */
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'username' => ['required', 'string', 'min:3', 'max:50', 'regex:/^[A-Za-z0-9._-]+$/', 'unique:users,username'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8'],
            'first_name' => ['required', 'string', 'max:100'],
            'last_name' => ['nullable', 'string', 'max:100'],
            'phone' => ['nullable', 'string', 'max:20'],
            'status' => ['nullable', Rule::in(['active', 'inactive', 'suspended'])],
            'user_type' => ['nullable', Rule::in(['super_admin', 'admin', 'teacher', 'staff', 'student', 'parent'])],
            'roles' => ['nullable', 'array'],
            'roles.*' => ['string', 'exists:roles,name'],
            ...$this->linkedSectionRules(),
        ]);

        $userType = $data['user_type'] ?? 'staff';

        // Super admin accounts are global (tenant null); others belong to the active tenant
        $tenantId = $userType === 'super_admin' ? null : $this->currentTenantId($request);
        if ($userType !== 'super_admin' && ! $tenantId) {
            return $this->error('Konteks sekolah (tenant) tidak ditemukan. Pilih sekolah terlebih dahulu.', 422);
        }

        $user = DB::transaction(function () use ($data, $userType, $tenantId) {
            $user = User::create([
                'tenant_id' => $tenantId,
                'username' => $data['username'],
                'email' => $data['email'],
                'password' => Hash::make($data['password']),
                'status' => $data['status'] ?? 'active',
                'user_type' => $userType,
            ]);

            // Verified by the admin who created the account
            $user->forceFill(['email_verified_at' => now()])->save();

            UserProfile::create([
                'user_id' => $user->id,
                'first_name' => $data['first_name'],
                'last_name' => $data['last_name'] ?? null,
                'phone' => $data['phone'] ?? null,
            ]);

            if (! empty($data['roles'])) {
                $user->syncRoles($data['roles']);
            }

            $this->syncLinkedRecords($user, $data, $tenantId);

            return $user;
        });

        $user->load(['roles', 'profile', 'teacher', 'staff', 'guardianStudents.student.user.profile']);

        return $this->success(new UserResource($user), 'Pengguna berhasil ditambahkan', 201);
    }

    /**
     * List available roles for the role picker.
     */
    public function roles(): JsonResponse
    {
        $roles = \Spatie\Permission\Models\Role::orderBy('name')
            ->get()
            ->map(fn ($role) => ['id' => (string) $role->id, 'name' => $role->name]);

        return $this->success($roles);
    }

    /**
     * Display the specified user.
     */
    public function show(User $user): JsonResponse
    {
        $user->load(['roles', 'profile', 'teacher', 'staff', 'guardianStudents.student.user.profile']);

        return $this->success(new UserResource($user));
    }

    /**
     * Update the specified user, profile, and role.
     */
    public function update(Request $request, User $user): JsonResponse
    {
        $data = $request->validate([
            'username' => [
                'sometimes', 'string', 'min:3', 'max:50', 'regex:/^[A-Za-z0-9._-]+$/',
                Rule::unique('users', 'username')->ignore($user->id),
            ],
            'email' => [
                'sometimes', 'string', 'email', 'max:255',
                Rule::unique('users', 'email')->ignore($user->id),
            ],
            'password' => ['nullable', 'string', 'min:8'],
            'first_name' => ['sometimes', 'string', 'max:100'],
            'last_name' => ['nullable', 'string', 'max:100'],
            'phone' => ['nullable', 'string', 'max:20'],
            'status' => ['nullable', Rule::in(['active', 'inactive', 'suspended'])],
            'user_type' => ['nullable', Rule::in(['super_admin', 'admin', 'teacher', 'staff', 'student', 'parent'])],
            'roles' => ['nullable', 'array'],
            'roles.*' => ['string', 'exists:roles,name'],
            ...$this->linkedSectionRules(),
        ]);

        DB::transaction(function () use ($data, $user, $request) {
            $userFields = array_intersect_key($data, array_flip(['username', 'email', 'status', 'user_type']));

            if (! empty($data['password'])) {
                $userFields['password'] = Hash::make($data['password']);
            }

            if ($userFields !== []) {
                $user->update($userFields);
            }

            $profileFields = array_intersect_key($data, array_flip(['first_name', 'last_name', 'phone']));
            if ($profileFields !== []) {
                UserProfile::updateOrCreate(['user_id' => $user->id], $profileFields);
            }

            if (isset($data['roles'])) {
                $user->syncRoles($data['roles']);
            }

            $this->syncLinkedRecords(
                $user,
                $data,
                $user->tenant_id ?? $this->currentTenantId($request)
            );
        });

        $user->refresh()->load(['roles', 'profile', 'teacher', 'staff', 'guardianStudents.student.user.profile']);

        return $this->success(new UserResource($user), 'Pengguna berhasil diperbarui');
    }

    /**
     * Aturan validasi seksi data tertaut (R1 + R8) untuk store & update.
     *
     * @return array<string, mixed>
     */
    private function linkedSectionRules(): array
    {
        return [
            'teacher' => ['nullable', 'array'],
            'teacher.nip' => ['nullable', 'string', 'max:50'],
            'teacher.join_date' => ['nullable', 'date'],
            'teacher.employment_status' => ['nullable', Rule::in(['permanent', 'contract', 'honorary', 'part_time'])],
            'teacher.education_level' => ['nullable', 'string', 'max:20'],
            'staff' => ['nullable', 'array'],
            'staff.id' => ['nullable', 'uuid', 'exists:staff,id'], // Link ke existing staff
            'staff.employee_id' => ['nullable', 'string', 'max:50'],
            'staff.join_date' => ['nullable', 'date'],
            'staff.employment_status' => ['nullable', Rule::in(['permanent', 'contract', 'honorary', 'part_time'])],
            'guardian_students' => ['nullable', 'array'],
            'guardian_students.*.student_id' => ['required', 'uuid', 'exists:students,id'],
            'guardian_students.*.relationship' => ['required', Rule::in(['father', 'mother', 'guardian', 'other'])],
            'guardian_students.*.is_primary_contact' => ['boolean'],
        ];
    }

    /**
     * Buat/perbarui record tertaut dalam transaksi yang sama (R1):
     * data kepegawaian guru, staf, dan penautan anak (R8).
     * Kunci yang tidak dikirim tidak disentuh; `guardian_students` yang
     * dikirim disinkronkan penuh (lepas = kosongkan user_id, baris tetap).
     */
    private function syncLinkedRecords(User $user, array $data, ?string $tenantId): void
    {
        if (array_key_exists('teacher', $data) && $data['teacher'] !== null) {
            $t = $data['teacher'];

            if (! empty($t['nip'])) {
                $taken = Teacher::withoutTenant()
                    ->where('tenant_id', $tenantId)
                    ->where('nip', $t['nip'])
                    ->where('user_id', '!=', $user->id)
                    ->exists();
                if ($taken) {
                    abort(422, 'NIP sudah digunakan guru lain.');
                }
            }

            Teacher::withoutTenant()->updateOrCreate(
                ['user_id' => $user->id],
                [
                    'tenant_id' => $tenantId,
                    'nip' => $t['nip'] ?? null,
                    'join_date' => $t['join_date'] ?? now()->toDateString(),
                    'employment_status' => $t['employment_status'] ?? 'permanent',
                    'education_level' => $t['education_level'] ?? null,
                    'status' => 'active',
                ]
            );
        }

        if (array_key_exists('staff', $data) && $data['staff'] !== null) {
            $s = $data['staff'];

            // Jika ada staff.id, link ke existing staff record
            if (! empty($s['id'])) {
                $existingStaff = Staff::withoutTenant()->find($s['id']);
                if ($existingStaff) {
                    // Pastikan staff belum tertaut ke user lain
                    if ($existingStaff->user_id && $existingStaff->user_id !== $user->id) {
                        abort(422, 'Data staf sudah tertaut ke pengguna lain.');
                    }
                    $existingStaff->update(['user_id' => $user->id]);
                }
            } else {
                // Buat atau update staff baru
                Staff::withoutTenant()->updateOrCreate(
                    ['user_id' => $user->id],
                    [
                        'tenant_id' => $tenantId,
                        'employee_id' => $s['employee_id'] ?? null,
                        'join_date' => $s['join_date'] ?? now()->toDateString(),
                        'employment_status' => $s['employment_status'] ?? 'permanent',
                        'status' => 'active',
                    ]
                );
            }
        }

        if (array_key_exists('guardian_students', $data) && is_array($data['guardian_students'])) {
            $this->syncGuardianStudents($user, $data['guardian_students'], $tenantId, $data);
        }
    }

    /**
     * Tautkan akun orang tua ke siswa (R8). Bila siswa sudah punya baris
     * wali tanpa akun yang cocok (nama/HP sama dengan profil), baris itu
     * ditautkan — bukan membuat duplikat. Melepas tautan hanya
     * mengosongkan user_id; data wali milik siswa tetap utuh.
     */
    private function syncGuardianStudents(User $user, array $entries, ?string $tenantId, array $data): void
    {
        $fullName = trim(($data['first_name'] ?? $user->first_name ?? '') . ' ' . ($data['last_name'] ?? $user->last_name ?? ''));
        $phone = $data['phone'] ?? $user->phone;
        $keptStudentIds = [];

        foreach ($entries as $entry) {
            $keptStudentIds[] = $entry['student_id'];

            $attrs = [
                'relationship' => $entry['relationship'],
                'is_primary_contact' => (bool) ($entry['is_primary_contact'] ?? false),
            ];

            // Sudah tertaut ke akun ini?
            $existing = StudentGuardian::where('student_id', $entry['student_id'])
                ->where('user_id', $user->id)
                ->first();

            if ($existing) {
                $existing->update($attrs);
                continue;
            }

            // Cocokkan baris wali tanpa akun via HP atau nama (hindari duplikat)
            $orphan = StudentGuardian::where('student_id', $entry['student_id'])
                ->whereNull('user_id')
                ->where(function ($q) use ($phone, $fullName) {
                    $q->when($phone, fn ($qq) => $qq->orWhere('phone', $phone))
                        ->when($fullName !== '', fn ($qq) => $qq->orWhere('name', 'ilike', $fullName));
                })
                ->first();

            if ($orphan) {
                $orphan->update(['user_id' => $user->id] + $attrs);
                continue;
            }

            StudentGuardian::create([
                'tenant_id' => $tenantId,
                'student_id' => $entry['student_id'],
                'user_id' => $user->id,
                'name' => $fullName !== '' ? $fullName : ($user->username ?? 'Wali'),
                'phone' => $phone,
                'email' => $user->email,
            ] + $attrs);
        }

        // Lepas tautan untuk siswa yang tidak lagi dipilih
        StudentGuardian::where('user_id', $user->id)
            ->whereNotIn('student_id', $keptStudentIds)
            ->update(['user_id' => null]);
    }

    /**
     * Opsi staf yang belum tertaut ke akun (untuk dropdown pemilihan).
     * Jika $userId diberikan (edit mode), staf yang sudah tertaut ke user tsb juga dikembalikan.
     */
    /**
     * Akun pengguna yang boleh ditautkan ke data master (kebalikan arah dari
     * staffOptions()): akun bertipe tertentu yang BELUM punya baris di tabel
     * data masternya, sehingga tampil di menu Pengguna tapi hilang di menu
     * Data Guru/Staf/Siswa.
     *
     * Dipakai oleh form "Tambah" ketiga menu itu untuk menawarkan opsi
     * "tautkan ke akun yang sudah ada" — tanpa ini satu-satunya jalan adalah
     * membuat akun kedua yang duplikat untuk orang yang sama.
     */
    public function linkableUsers(Request $request): JsonResponse
    {
        $data = $request->validate([
            'type' => ['required', Rule::in(['teacher', 'staff', 'student'])],
            'search' => ['nullable', 'string', 'max:100'],
        ]);

        $table = match ($data['type']) {
            'teacher' => 'teachers',
            'staff' => 'staff',
            'student' => 'students',
        };

        $search = trim((string) ($data['search'] ?? ''));
        $tenantId = $this->currentTenantId($request);

        $users = User::query()
            ->with('profile')
            ->where('user_type', $data['type'])
            ->when($tenantId, fn ($q) => $q->where('tenant_id', $tenantId))
            ->whereNotExists(function ($q) use ($table) {
                $q->selectRaw(1)
                    ->from($table)
                    ->whereColumn($table . '.user_id', 'users.id');

                if (Schema::hasColumn($table, 'deleted_at')) {
                    $q->whereNull($table . '.deleted_at');
                }
            })
            ->when($search !== '', fn ($q) => $q->where(function ($query) use ($search) {
                $query->where('username', 'ilike', "%{$search}%")
                    ->orWhere('email', 'ilike', "%{$search}%")
                    ->orWhereHas('profile', fn ($p) => $p
                        ->where('first_name', 'ilike', "%{$search}%")
                        ->orWhere('last_name', 'ilike', "%{$search}%"));
            }))
            ->orderBy('username')
            ->limit(50)
            ->get()
            ->map(fn (User $u) => [
                'id' => $u->id,
                'username' => $u->username,
                'email' => $u->email,
                'full_name' => $u->full_name,
                'status' => $u->status,
            ]);

        return $this->success($users);
    }

    public function staffOptions(Request $request): JsonResponse
    {
        $search = trim((string) $request->get('search', ''));
        $currentUserId = $request->get('user_id'); // Untuk edit: tampilkan staf yg sudah tertaut

        $staff = Staff::query()
            ->with(['user.profile', 'department', 'position'])
            ->where(function ($q) use ($currentUserId) {
                $q->whereNull('user_id');
                if ($currentUserId) {
                    $q->orWhere('user_id', $currentUserId);
                }
            })
            ->when($search !== '', function ($q) use ($search) {
                $q->where(function ($query) use ($search) {
                    $query->where('employee_id', 'ilike', "%{$search}%")
                        ->orWhereHas('user.profile', fn ($p) => $p
                            ->where('first_name', 'ilike', "%{$search}%")
                            ->orWhere('last_name', 'ilike', "%{$search}%"))
                        ->orWhereHas('department', fn ($d) => $d->where('name', 'ilike', "%{$search}%"))
                        ->orWhereHas('position', fn ($p) => $p->where('name', 'ilike', "%{$search}%"));
                });
            })
            ->orderByRaw('employee_id IS NULL, employee_id ASC')
            ->limit(50)
            ->get()
            ->map(fn (Staff $s) => [
                'id' => $s->id,
                'employee_id' => $s->employee_id,
                'join_date' => $s->join_date?->format('Y-m-d'),
                'employment_status' => $s->employment_status,
                'department_name' => $s->department?->name,
                'position_name' => $s->position?->name,
                'user_id' => $s->user_id,
                'user_name' => $s->user?->full_name,
            ]);

        return $this->success($staff);
    }

    /**
     * Opsi siswa untuk pemilih penautan orang tua (pencarian nama/NIS).
     */
    public function studentOptions(Request $request): JsonResponse
    {
        $search = trim((string) $request->get('search', ''));

        $students = Student::query()
            ->with(['user.profile', 'currentClass'])
            ->where('status', 'active')
            ->when($search !== '', function ($q) use ($search) {
                $q->where(function ($query) use ($search) {
                    $query->where('nis', 'ilike', "%{$search}%")
                        ->orWhereHas('user.profile', fn ($p) => $p
                            ->where('first_name', 'ilike', "%{$search}%")
                            ->orWhere('last_name', 'ilike', "%{$search}%"));
                });
            })
            ->limit(20)
            ->get()
            ->map(fn (Student $s) => [
                'id' => $s->id,
                'nis' => $s->nis,
                'name' => $s->user?->full_name,
                'class_name' => $s->currentClass?->name,
            ]);

        return $this->success($students);
    }

    /**
     * Soft delete the specified user.
     */
    public function destroy(Request $request, User $user): JsonResponse
    {
        if ($user->id === $request->user()->id) {
            return $this->error('Tidak dapat menghapus akun sendiri', 422);
        }

        $user->delete();

        return $this->success(null, 'Pengguna berhasil dihapus');
    }

    /**
     * Activate the specified user.
     */
    public function activate(User $user): JsonResponse
    {
        $user->update(['status' => 'active']);
        $user->load(['roles', 'profile']);

        return $this->success(new UserResource($user), 'Pengguna berhasil diaktifkan');
    }

    /**
     * Deactivate the specified user.
     */
    public function deactivate(Request $request, User $user): JsonResponse
    {
        if ($user->id === $request->user()->id) {
            return $this->error('Tidak dapat menonaktifkan akun sendiri', 422);
        }

        $user->update(['status' => 'inactive']);
        $user->load(['roles', 'profile']);

        return $this->success(new UserResource($user), 'Pengguna berhasil dinonaktifkan');
    }

    /**
     * Reset the user's password to a new value supplied by the admin.
     */
    public function resetPassword(Request $request, User $user): JsonResponse
    {
        $data = $request->validate([
            'password' => ['required', 'string', 'min:8'],
        ]);

        $user->update(['password' => Hash::make($data['password'])]);
        // Admin hanya mereset; pengguna wajib menentukan password sendiri saat login berikutnya
        $user->markPasswordMustChange();

        return $this->success(null, 'Password berhasil direset');
    }
}
