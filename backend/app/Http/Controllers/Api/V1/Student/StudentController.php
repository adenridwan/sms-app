<?php

namespace App\Http\Controllers\Api\V1\Student;

use App\Http\Controllers\Api\ApiController;
use App\Http\Requests\Student\StoreStudentRequest;
use App\Http\Requests\Student\UpdateStudentRequest;
use App\Http\Resources\StudentCollection;
use App\Http\Resources\StudentResource;
use App\Infrastructure\Persistence\Eloquent\Student\Student;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class StudentController extends ApiController
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request): JsonResponse
    {
        $query = Student::with(['user.profile', 'currentClass'])
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

            // Create user account
            $user = User::create([
                'username' => $data['username'] ?? Str::slug($data['first_name'] . '-' . Str::random(4)),
                'email' => $data['email'],
                'password' => Hash::make($data['password'] ?? 'password123'),
                'first_name' => $data['first_name'],
                'last_name' => $data['last_name'] ?? null,
                'phone' => $data['phone'] ?? null,
                'is_active' => true,
            ]);

            // Assign student role
            $user->assignRole('siswa');

            // Handle photo upload
            $photoPath = null;
            if ($request->hasFile('photo')) {
                $photoPath = $request->file('photo')->store('students', 'public');
            }

            // Create student
            $student = Student::create([
                'user_id' => $user->id,
                'nis' => $data['nis'],
                'nisn' => $data['nisn'] ?? null,
                'nik' => $data['nik'] ?? null,
                'gender' => $data['gender'],
                'birth_place' => $data['birth_place'] ?? null,
                'birth_date' => $data['birth_date'] ?? null,
                'religion' => $data['religion'] ?? null,
                'address' => $data['address'] ?? null,
                'phone' => $data['phone'] ?? null,
                'previous_school' => $data['previous_school'] ?? null,
                'entry_year' => $data['entry_year'],
                'entry_class' => $data['entry_class'] ?? null,
                'entry_semester' => $data['entry_semester'] ?? 1,
                'status' => 'active',
                'photo' => $photoPath,
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
        $student->load(['user', 'currentClass', 'parents.user']);

        return $this->success(new StudentResource($student));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateStudentRequest $request, Student $student): JsonResponse
    {
        $data = $request->validated();

        try {
            DB::beginTransaction();

            // Update user data
            $userData = array_filter([
                'first_name' => $data['first_name'] ?? null,
                'last_name' => $data['last_name'] ?? null,
                'email' => $data['email'] ?? null,
                'phone' => $data['phone'] ?? null,
            ]);

            if (!empty($userData)) {
                $student->user->update($userData);
            }

            // Handle photo upload
            if ($request->hasFile('photo')) {
                // Delete old photo
                if ($student->photo) {
                    Storage::disk('public')->delete($student->photo);
                }
                $data['photo'] = $request->file('photo')->store('students', 'public');
            }

            // Remove user-related fields
            unset($data['first_name'], $data['last_name'], $data['email'], $data['username'], $data['password']);

            // Update student
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
     * Remove the specified resource from storage.
     */
    public function destroy(Student $student): JsonResponse
    {
        try {
            DB::beginTransaction();

            // Delete photo if exists
            if ($student->photo) {
                Storage::disk('public')->delete($student->photo);
            }

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

        try {
            DB::beginTransaction();

            $students = Student::whereIn('id', $request->ids)->get();

            foreach ($students as $student) {
                if ($student->photo) {
                    Storage::disk('public')->delete($student->photo);
                }
                $student->user->delete();
                $student->delete();
            }

            DB::commit();

            return $this->success(null, count($request->ids) . ' siswa berhasil dihapus');
        } catch (\Exception $e) {
            DB::rollBack();

            return $this->error('Gagal menghapus siswa: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Export students.
     */
    public function export(Request $request): JsonResponse
    {
        // TODO: Implement export functionality
        return $this->success(['url' => ''], 'Export sedang diproses');
    }

    /**
     * Import students.
     */
    public function import(Request $request): JsonResponse
    {
        $request->validate([
            'file' => ['required', 'file', 'mimes:xlsx,xls,csv', 'max:10240'],
        ]);

        // TODO: Implement import functionality
        return $this->success(null, 'Import sedang diproses');
    }
}
