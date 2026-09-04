<?php

namespace App\Http\Controllers\Api\V1\Staff;

use App\Http\Controllers\Api\ApiController;
use App\Http\Requests\Staff\StoreStaffRequest;
use App\Http\Requests\Staff\UpdateStaffRequest;
use App\Http\Resources\StaffResource;
use App\Infrastructure\Persistence\Eloquent\Auth\UserProfile;
use App\Infrastructure\Persistence\Eloquent\Staff\Department;
use App\Infrastructure\Persistence\Eloquent\Staff\Position;
use App\Infrastructure\Persistence\Eloquent\Staff\Staff;
use App\Services\EmailGenerator;
use App\Services\StaffRegistrar;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Data Staf (master): identitas, kepegawaian, dan akun login staf non-teaching.
 */
class StaffController extends ApiController
{
    public function __construct(
        private StaffRegistrar $registrar,
        private EmailGenerator $emailGenerator,
    ) {}

    /**
     * Display a listing of the resource.
     */
    public function index(Request $request): JsonResponse
    {
        $query = Staff::with(['user.profile', 'department', 'position'])
            ->when($request->search, function ($q, $search) {
                $q->where(function ($query) use ($search) {
                    $query->where('employee_id', 'ilike', "%{$search}%")
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
            ->when($request->department_id, fn ($q, $deptId) => $q->where('department_id', $deptId))
            ->when($request->gender, fn ($q, $gender) => $q->whereHas('user.profile', fn ($q) => $q->where('gender', $gender)));

        $sortField = in_array($request->get('sort'), ['employee_id', 'created_at'], true)
            ? $request->get('sort')
            : 'created_at';
        $sortDirection = $request->get('direction') === 'asc' ? 'asc' : 'desc';
        $query->orderBy($sortField, $sortDirection);

        $perPage = min((int) $request->get('per_page', 15), 100);
        $staff = $query->paginate($perPage);

        return $this->success(StaffResource::collection($staff)->response()->getData(true));
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreStaffRequest $request): JsonResponse
    {
        $data = $request->validated();

        $tenantId = $this->currentTenantId($request);
        if (! $tenantId) {
            return $this->error('Konteks sekolah (tenant) tidak ditemukan. Pilih sekolah terlebih dahulu.', 422);
        }

        if (trim((string) ($data['email'] ?? '')) === '' && ! $this->emailGenerator->hasDomain($tenantId)) {
            return $this->error(EmailGenerator::domainMissingMessage(), 422);
        }

        ['staff' => $staff, 'username' => $username, 'password' => $initialPassword, 'email' => $email] =
            $this->registrar->create($data, $tenantId);

        return $this->success([
            ...(new StaffResource($staff))->resolve(),
            'initial_username' => $username,
            'initial_password' => $initialPassword,
            'initial_email' => $email,
        ], 'Staf berhasil ditambahkan', 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(Staff $staff): JsonResponse
    {
        $staff->load(['user.profile', 'department', 'position']);

        return $this->success(new StaffResource($staff));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateStaffRequest $request, Staff $staff): JsonResponse
    {
        $data = $request->validated();

        DB::transaction(function () use ($data, $staff) {
            $userFields = array_intersect_key($data, array_flip(['email', 'contact_email']));
            if ($userFields !== []) {
                $staff->user->update($userFields);
            }

            $profileFields = array_intersect_key($data, array_flip([
                'first_name', 'last_name', 'phone', 'gender', 'birth_place',
                'birth_date', 'religion', 'address', 'id_number',
            ]));
            if ($profileFields !== []) {
                UserProfile::updateOrCreate(['user_id' => $staff->user_id], $profileFields);
            }

            $staffFields = array_intersect_key($data, array_flip([
                'employee_id', 'department_id', 'position_id', 'join_date',
                'employment_status', 'status', 'education_level',
            ]));
            if ($staffFields !== []) {
                $staff->update($staffFields);
            }
        });

        $staff->refresh()->load(['user.profile', 'department', 'position']);

        return $this->success(new StaffResource($staff), 'Staf berhasil diperbarui');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Request $request, Staff $staff): JsonResponse
    {
        abort_unless($request->user()->can('staff.delete'), 403);

        if ($staff->user_id === $request->user()->id) {
            return $this->error('Tidak dapat menghapus akun sendiri', 422);
        }

        DB::transaction(function () use ($staff) {
            $staff->user?->delete();
            $staff->delete();
        });

        return $this->success(null, 'Staf berhasil dihapus');
    }

    /**
     * Upload/ganti foto profil staf.
     */
    public function uploadPhoto(Request $request, Staff $staff): JsonResponse
    {
        abort_unless($request->user()->can('staff.update'), 403);

        $request->validate([
            'photo' => ['required', 'image', 'mimes:jpeg,png,jpg', 'max:2048'],
        ]);

        $user = $staff->user;

        if ($user->avatar) {
            Storage::disk('public')->delete($user->avatar);
        }

        $user->update(['avatar' => $request->file('photo')->store('avatars', 'public')]);

        return $this->success(['avatar_url' => asset('storage/' . $user->avatar)], 'Foto berhasil diperbarui');
    }

    /**
     * Hapus foto profil staf.
     */
    public function deletePhoto(Request $request, Staff $staff): JsonResponse
    {
        abort_unless($request->user()->can('staff.update'), 403);

        $user = $staff->user;

        if ($user->avatar) {
            Storage::disk('public')->delete($user->avatar);
            $user->update(['avatar' => null]);
        }

        return $this->success(null, 'Foto berhasil dihapus');
    }

    /**
     * Get departments list for dropdown.
     */
    public function departments(Request $request): JsonResponse
    {
        $departments = Department::where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name', 'code']);

        return $this->success($departments);
    }

    /**
     * Get positions list for dropdown.
     */
    public function positions(Request $request): JsonResponse
    {
        $positions = Position::where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name', 'code']);

        return $this->success($positions);
    }

    /**
     * Get staff options for dropdown (used in Users page).
     */
    public function options(Request $request): JsonResponse
    {
        $staff = Staff::with(['user.profile'])
            ->where('status', 'active')
            ->get()
            ->map(fn (Staff $s) => [
                'id' => $s->id,
                'employee_id' => $s->employee_id,
                'full_name' => $s->user?->full_name,
                'join_date' => $s->join_date?->format('Y-m-d'),
                'employment_status' => $s->employment_status,
            ]);

        return $this->success($staff);
    }
}
