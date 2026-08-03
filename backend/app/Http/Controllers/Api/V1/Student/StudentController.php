<?php

namespace App\Http\Controllers\Api\V1\Student;

use App\Exports\Student\StudentsExport;
use App\Exports\Student\StudentsTemplateExport;
use App\Http\Controllers\Api\ApiController;
use App\Http\Requests\Student\StoreStudentRequest;
use App\Http\Requests\Student\UpdateStudentRequest;
use App\Http\Resources\StudentCollection;
use App\Http\Resources\StudentResource;
use App\Imports\Student\StudentsImport;
use App\Infrastructure\Persistence\Eloquent\Auth\UserProfile;
use App\Infrastructure\Persistence\Eloquent\Student\Student;
use App\Models\User;
use App\Services\StudentRegistrar;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Maatwebsite\Excel\Excel as ExcelFormat;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class StudentController extends ApiController
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request): JsonResponse
    {
        $query = Student::visibleTo($request->user())
            ->with(['user.profile', 'currentClass'])
            ->when($request->search, function ($q, $search) {
                $q->where(function ($query) use ($search) {
                    $query->where('nis', 'ilike', "%{$search}%")
                        ->orWhere('nisn', 'ilike', "%{$search}%")
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
            ->when($request->status, fn($q, $status) => $q->where('status', $status))
            ->when($request->gender, fn($q, $gender) => $q->whereHas('user.profile', fn($q) => $q->where('gender', $gender)))
            ->when($request->class_id, fn($q, $classId) => $q->whereHas('currentClass', fn($q) => $q->where('classrooms.id', $classId)));

        // Sorting
        $sortField = $request->get('sort', 'created_at');
        $sortDirection = $request->get('direction', 'desc');

        if ($sortField === 'name') {
            $query->join('users', 'students.user_id', '=', 'users.id')
                ->orderBy('users.first_name', $sortDirection)
                ->select('students.*');
        } else {
            $query->orderBy($sortField, $sortDirection);
        }

        $perPage = $request->get('per_page', 15);
        $students = $query->paginate($perPage);

        return $this->collection(new StudentCollection($students));
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreStudentRequest $request): JsonResponse
    {
        $data = $request->validated();

        try {
            DB::beginTransaction();

            // Create user account. Profile-only fields (gender/birth_place/
            // birth_date/religion/address/id_number) are NOT columns on
            // `students` — they live on `user_profiles`. The legacy
            // App\Models\User mutators route them there via
            // pendingProfileData; passing them to Student::create() below
            // would silently drop them (not in Student::$fillable).
            $user = User::create([
                'username' => $data['username'] ?? Str::slug($data['first_name'] . '-' . Str::random(4)),
                'email' => $data['email'],
                'password' => Hash::make($data['password'] ?? 'password123'),
                'first_name' => $data['first_name'],
                'last_name' => $data['last_name'] ?? null,
                'phone' => $data['phone'] ?? null,
                'gender' => $data['gender'],
                'birth_place' => $data['birth_place'] ?? null,
                'birth_date' => $data['birth_date'] ?? null,
                'religion' => $data['religion'] ?? null,
                'address' => $data['address'] ?? null,
                'id_number' => $data['nik'] ?? null,
                'is_active' => true,
            ]);

            // Assign student role
            $user->assignRole('siswa');

            // Create student (only real students.* columns)
            $student = Student::create([
                'user_id' => $user->id,
                'nis' => $data['nis'],
                'nisn' => $data['nisn'] ?? null,
                'previous_school' => $data['previous_school'] ?? null,
                'entry_date' => now()->toDateString(),
                'entry_type' => 'new',
                'status' => 'active',
            ]);

            DB::commit();

            $student->load(['user', 'currentClass']);

            return $this->success(
                new StudentResource($student),
                'Siswa berhasil ditambahkan',
                201
            );
        } catch (\Exception $e) {
            DB::rollBack();

            return $this->error('Gagal menambahkan siswa: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(Student $student): JsonResponse
    {
        $this->authorize('view', $student);

        $student->load(['user', 'currentClass', 'parents.user']);

        return $this->success(new StudentResource($student));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateStudentRequest $request, Student $student): JsonResponse
    {
        $this->authorize('update', $student);

        $data = $request->validated();

        try {
            DB::beginTransaction();

            // Update users.* (email only — first_name/last_name/phone/etc.
            // below all live on user_profiles, not users or students).
            $userFields = array_intersect_key($data, array_flip(['email']));
            if ($userFields !== []) {
                $student->user->update($userFields);
            }

            // Update user_profiles.* directly (mirrors TeacherController::
            // update() — $student->user resolves the base Auth\User class,
            // which has no mass-assignable profile mutators, so routing
            // these through $student->user->update() would throw
            // MassAssignmentException; UserProfile::updateOrCreate is the
            // pattern that actually works).
            $profileFields = array_intersect_key($data, array_flip([
                'first_name', 'last_name', 'phone', 'gender', 'birth_place',
                'birth_date', 'religion', 'address',
            ]));
            if (array_key_exists('nik', $data)) {
                $profileFields['id_number'] = $data['nik'];
            }
            if ($profileFields !== []) {
                UserProfile::updateOrCreate(['user_id' => $student->user_id], $profileFields);
            }

            // Remove fields that aren't real students.* columns
            unset(
                $data['first_name'], $data['last_name'], $data['email'], $data['username'], $data['password'],
                $data['gender'], $data['birth_place'], $data['birth_date'], $data['religion'], $data['address'], $data['nik'],
                $data['phone'], $data['entry_year'], $data['entry_class'], $data['entry_semester']
            );

            // Update student (only real students.* columns remain in $data)
            $student->update($data);

            DB::commit();

            $student->load(['user', 'currentClass', 'parents.user']);

            return $this->success(new StudentResource($student), 'Siswa berhasil diperbarui');
        } catch (\Exception $e) {
            DB::rollBack();

            return $this->error('Gagal memperbarui siswa: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Export data siswa (xlsx/csv) — kolomnya sama dengan template import.
     */
    public function export(Request $request): BinaryFileResponse
    {
        abort_unless($request->user()->can('students.export'), 403);

        [$extension, $writerType] = $this->fileFormat($request);

        return Excel::download(new StudentsExport(), "siswa.{$extension}", $writerType);
    }

    /**
     * Unduh template import siswa (xlsx/csv).
     */
    public function template(Request $request): BinaryFileResponse
    {
        abort_unless($request->user()->can('students.import'), 403);

        [$extension, $writerType] = $this->fileFormat($request);

        return Excel::download(new StudentsTemplateExport(), "template-import-siswa.{$extension}", $writerType);
    }

    /**
     * Import siswa dari file CSV/Excel (upsert berdasarkan NIS).
     */
    public function import(Request $request, StudentRegistrar $registrar): JsonResponse
    {
        abort_unless($request->user()->can('students.import'), 403);

        $tenantId = $this->currentTenantId($request);
        if (! $tenantId) {
            return $this->error('Konteks sekolah (tenant) tidak ditemukan. Pilih sekolah terlebih dahulu.', 422);
        }

        $request->validate([
            'file' => ['required', 'file', 'mimes:xlsx,xls,csv,txt', 'max:5120'],
        ]);

        $import = new StudentsImport($tenantId, $registrar);
        Excel::import($import, $request->file('file'));

        return $this->success([
            'created' => $import->created,
            'updated' => $import->updated,
            'errors' => $import->errors,
        ], "Import siswa selesai: {$import->created} ditambahkan, {$import->updated} diperbarui.");
    }

    /**
     * Format berkas untuk export/template: xlsx (default) atau csv.
     *
     * @return array{0: string, 1: string}
     */
    private function fileFormat(Request $request): array
    {
        return strtolower((string) $request->get('format')) === 'csv'
            ? ['csv', ExcelFormat::CSV]
            : ['xlsx', ExcelFormat::XLSX];
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Student $student): JsonResponse
    {
        $this->authorize('delete', $student);

        try {
            DB::beginTransaction();

            // Soft delete user account
            $student->user->delete();

            // Soft delete student
            $student->delete();

            DB::commit();

            return $this->success(null, 'Siswa berhasil dihapus');
        } catch (\Exception $e) {
            DB::rollBack();

            return $this->error('Gagal menghapus siswa: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Bulk delete students.
     */
    public function bulkDestroy(Request $request): JsonResponse
    {
        $request->validate([
            'ids' => ['required', 'array'],
            'ids.*' => ['required', 'uuid', 'exists:students,id'],
        ]);

        $students = Student::visibleTo($request->user())->whereIn('id', $request->ids)->get();

        foreach ($students as $student) {
            $this->authorize('delete', $student);
        }

        try {
            DB::beginTransaction();

            foreach ($students as $student) {
                $student->user->delete();
                $student->delete();
            }

            DB::commit();

            return $this->success(null, $students->count() . ' siswa berhasil dihapus');
        } catch (\Exception $e) {
            DB::rollBack();

            return $this->error('Gagal menghapus siswa: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Upload/replace the student's profile photo (stored on users.avatar,
     * same convention as TeacherController::uploadPhoto — see
     * ATTENDANCE-PLAN.md Fase 3a).
     */
    public function uploadPhoto(Request $request, Student $student): JsonResponse
    {
        abort_unless($request->user()->can('students.update'), 403);

        $request->validate([
            'photo' => ['required', 'image', 'mimes:jpeg,png,jpg', 'max:2048'],
        ]);

        $user = $student->user;

        if ($user->avatar) {
            Storage::disk('public')->delete($user->avatar);
        }

        $user->update(['avatar' => $request->file('photo')->store('avatars', 'public')]);

        return $this->success(['photo_url' => asset('storage/' . $user->avatar)], 'Foto berhasil diperbarui');
    }

    /**
     * Remove the student's profile photo.
     */
    public function deletePhoto(Request $request, Student $student): JsonResponse
    {
        abort_unless($request->user()->can('students.update'), 403);

        $user = $student->user;

        if ($user->avatar) {
            Storage::disk('public')->delete($user->avatar);
            $user->update(['avatar' => null]);
        }

        return $this->success(null, 'Foto berhasil dihapus');
    }

}
