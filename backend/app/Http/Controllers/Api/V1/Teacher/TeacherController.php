<?php

namespace App\Http\Controllers\Api\V1\Teacher;

use App\Http\Controllers\Api\ApiController;
use App\Http\Requests\Teacher\StoreTeacherRequest;
use App\Http\Requests\Teacher\UpdateTeacherRequest;
use App\Http\Resources\TeacherResource;
use App\Infrastructure\Persistence\Eloquent\Auth\User;
use App\Infrastructure\Persistence\Eloquent\Auth\UserProfile;
use App\Infrastructure\Persistence\Eloquent\Teacher\Teacher;
use App\Services\TeacherAssignmentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * Data Guru (master): identitas, kepegawaian, dan akun login guru.
 *
 * Penempatan kelas & mapel SENGAJA tidak dikelola di sini (lihat
 * TEACHER-MODULE-PLAN.md §2) — supaya tabel guru tidak berubah setiap
 * pergantian tahun ajaran. Kelola dari menu Kelas (wali/pengampu) dan
 * menu Mata Pelajaran (kompetensi).
 */
class TeacherController extends ApiController
{
    public function __construct(
        private TeacherAssignmentService $assignments,
    ) {}

    /**
     * Display a listing of the resource.
     */
    public function index(Request $request): JsonResponse
    {
        $query = Teacher::with(['user.profile'])
            ->when($request->search, function ($q, $search) {
                $q->where(function ($query) use ($search) {
                    $query->where('nip', 'ilike', "%{$search}%")
                        ->orWhere('nuptk', 'ilike', "%{$search}%")
                        ->orWhereHas('user', function ($q) use ($search) {
                            $q->where('username', 'ilike', "%{$search}%")
                                ->orWhere('email', 'ilike', "%{$search}%");
                        })
                        ->orWhereHas('user.profile', function ($q) use ($search) {
                            $q->where('first_name', 'ilike', "%{$search}%")
                                ->orWhere('last_name', 'ilike', "%{$search}%");
                        });
                });
            })
            ->when($request->status, fn ($q, $status) => $q->where('status', $status))
            ->when($request->employment_status, fn ($q, $es) => $q->where('employment_status', $es))
            ->when($request->gender, fn ($q, $gender) => $q->whereHas('user.profile', fn ($q) => $q->where('gender', $gender)));

        $sortField = in_array($request->get('sort'), ['nip', 'created_at'], true)
            ? $request->get('sort')
            : 'created_at';
        $sortDirection = $request->get('direction') === 'asc' ? 'asc' : 'desc';
        $query->orderBy($sortField, $sortDirection);

        $perPage = min((int) $request->get('per_page', 15), 100);
        $teachers = $query->paginate($perPage);

        return $this->success(TeacherResource::collection($teachers)->response()->getData(true));
    }

    /**
     * Store a newly created resource in storage.
     *
     * Membuat akun guru sekaligus (R1, ROLE-ACCESS-PLAN.md): users +
     * user_profiles + teachers dalam satu transaksi. Password awal =
     * tanggal lahir format ddmmyyyy; wajib diganti saat login pertama.
     */
    public function store(StoreTeacherRequest $request): JsonResponse
    {
        $data = $request->validated();

        $tenantId = $this->currentTenantId($request);
        if (! $tenantId) {
            return $this->error('Konteks sekolah (tenant) tidak ditemukan. Pilih sekolah terlebih dahulu.', 422);
        }

        $fullName = trim($data['first_name'] . ' ' . ($data['last_name'] ?? ''));
        $username = $this->generateUniqueUsername($fullName);
        $initialPassword = Carbon::parse($data['birth_date'])->format('dmY');

        $teacher = DB::transaction(function () use ($data, $tenantId, $username, $initialPassword) {
            $user = User::create([
                'tenant_id' => $tenantId,
                'username' => $username,
                'email' => $data['email'],
                'password' => Hash::make($initialPassword),
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

        return $this->success([
            ...(new TeacherResource($teacher))->resolve(),
            // Ditampilkan hanya sekali pada respons ini agar admin bisa
            // menyampaikan kredensial ke guru; tidak pernah disimpan ulang.
            'initial_username' => $username,
            'initial_password' => $initialPassword,
        ], 'Guru berhasil ditambahkan', 201);
    }

    /**
     * Display the specified resource (termasuk ringkasan dokumen — G2).
     */
    public function show(Teacher $teacher): JsonResponse
    {
        $teacher->load(['user.profile', 'media']);

        return $this->success(new TeacherResource($teacher));
    }

    /**
     * Ringkasan penugasan guru (read-only) untuk halaman Detail Guru —
     * lihat TeacherAssignmentService untuk alasan dipisah dari TeacherResource.
     */
    public function assignment(Teacher $teacher): JsonResponse
    {
        return $this->success($this->assignments->overview($teacher));
    }

    /**
     * Update the specified resource in storage.
     *
     * Password TIDAK diubah di sini secara sengaja — reset password
     * adalah aksi terpisah (menu Pengguna) yang juga menandai wajib
     * ganti password, konsisten dengan Admin\UserController.
     */
    public function update(UpdateTeacherRequest $request, Teacher $teacher): JsonResponse
    {
        $data = $request->validated();

        DB::transaction(function () use ($data, $teacher) {
            $userFields = array_intersect_key($data, array_flip(['email']));
            if ($userFields !== []) {
                $teacher->user->update($userFields);
            }

            $profileFields = array_intersect_key($data, array_flip([
                'first_name', 'last_name', 'phone', 'gender', 'birth_place',
                'birth_date', 'religion', 'address', 'id_number',
            ]));
            if ($profileFields !== []) {
                UserProfile::updateOrCreate(['user_id' => $teacher->user_id], $profileFields);
            }

            $teacherFields = array_intersect_key($data, array_flip([
                'nip', 'nuptk', 'join_date', 'employment_status', 'status',
                'certification_status', 'certification_number', 'education_level',
                'education_major', 'university', 'teaching_experience_years',
            ]));
            if (array_key_exists('phone', $data)) {
                $teacherFields['no_hp'] = $data['phone'];
            }
            if ($teacherFields !== []) {
                $teacher->update($teacherFields);
            }
        });

        $teacher->refresh()->load(['user.profile']);

        return $this->success(new TeacherResource($teacher), 'Guru berhasil diperbarui');
    }

    /**
     * Remove the specified resource from storage.
     *
     * Soft delete guru + akun terkait (akun langsung tidak bisa login
     * lagi), konsisten dengan siklus hidup di StudentController.
     */
    public function destroy(Request $request, Teacher $teacher): JsonResponse
    {
        if ($teacher->user_id === $request->user()->id) {
            return $this->error('Tidak dapat menghapus akun sendiri', 422);
        }

        DB::transaction(function () use ($teacher) {
            $teacher->user?->delete();
            $teacher->delete();
        });

        return $this->success(null, 'Guru berhasil dihapus');
    }

    /**
     * Upload/ganti foto profil guru (disimpan di users.avatar — satu
     * sumber foto dipakai bersama sidebar & dashboard, bukan kolom
     * terpisah di tabel teachers; lihat TEACHER-MODULE-PLAN.md keputusan #5).
     */
    public function uploadPhoto(Request $request, Teacher $teacher): JsonResponse
    {
        abort_unless($request->user()->can('teachers.update'), 403);

        $request->validate([
            'photo' => ['required', 'image', 'mimes:jpeg,png,jpg', 'max:2048'],
        ]);

        $user = $teacher->user;

        if ($user->avatar) {
            Storage::disk('public')->delete($user->avatar);
        }

        $user->update(['avatar' => $request->file('photo')->store('avatars', 'public')]);

        return $this->success(['avatar_url' => asset('storage/' . $user->avatar)], 'Foto berhasil diperbarui');
    }

    /**
     * Hapus foto profil guru.
     */
    public function deletePhoto(Request $request, Teacher $teacher): JsonResponse
    {
        abort_unless($request->user()->can('teachers.update'), 403);

        $user = $teacher->user;

        if ($user->avatar) {
            Storage::disk('public')->delete($user->avatar);
            $user->update(['avatar' => null]);
        }

        return $this->success(null, 'Foto berhasil dihapus');
    }

    /**
     * Upload dokumen pemberkasan ke salah satu koleksi (semua opsional —
     * TEACHER-MODULE-PLAN.md keputusan #6). KTP/NPWP singleFile: unggahan
     * baru otomatis menimpa yang lama (ditangani medialibrary).
     */
    public function uploadDocument(Request $request, Teacher $teacher): JsonResponse
    {
        abort_unless($request->user()->can('teachers.update'), 403);

        $data = $request->validate([
            'collection' => ['required', Rule::in(array_keys(Teacher::DOCUMENT_COLLECTIONS))],
            'file' => [
                'required', 'file',
                'mimes:pdf,jpg,jpeg,png',
                'max:' . Teacher::DOCUMENT_MAX_KB,
            ],
        ]);

        $teacher->addMediaFromRequest('file')->toMediaCollection($data['collection']);

        $teacher->load('media');

        return $this->success($teacher->documentsSummary(), 'Dokumen berhasil diunggah');
    }

    /**
     * Hapus satu dokumen.
     */
    public function deleteDocument(Request $request, Teacher $teacher, Media $media): JsonResponse
    {
        abort_unless($request->user()->can('teachers.update'), 403);

        if ($media->model_type !== Teacher::class || $media->model_id !== $teacher->id) {
            abort(404);
        }

        $media->delete();

        $teacher->load('media');

        return $this->success($teacher->documentsSummary(), 'Dokumen berhasil dihapus');
    }

    /**
     * Buat username unik dari nama (slug), tambahkan angka bila bentrok.
     */
    private function generateUniqueUsername(string $fullName): string
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
