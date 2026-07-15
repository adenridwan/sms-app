<?php

namespace App\Http\Controllers\Api\V1\Teacher;

use App\Http\Controllers\Api\ApiController;
use App\Http\Requests\Teacher\StoreTeacherRequest;
use App\Http\Requests\Teacher\UpdateTeacherRequest;
use App\Http\Resources\TeacherResource;
use App\Models\Teacher\Teacher;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class TeacherController extends ApiController
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request): JsonResponse
    {
        $query = Teacher::with(['user', 'subjects'])
            ->when($request->search, function ($q, $search) {
                $q->where(function ($query) use ($search) {
                    $query->where('nip', 'like', "%{$search}%")
                        ->orWhere('nuptk', 'like', "%{$search}%")
                        ->orWhereHas('user', function ($q) use ($search) {
                            $q->where('first_name', 'like', "%{$search}%")
                                ->orWhere('last_name', 'like', "%{$search}%")
                                ->orWhere('email', 'like', "%{$search}%");
                        });
                });
            })
            ->when($request->status, fn($q, $status) => $q->where('employment_status', $status))
            ->when($request->gender, fn($q, $gender) => $q->where('gender', $gender))
            ->when($request->subject_id, fn($q, $subjectId) => $q->whereHas('subjects', fn($q) => $q->where('id', $subjectId)));

        // Sorting
        $sortField = $request->get('sort', 'created_at');
        $sortDirection = $request->get('direction', 'desc');

        if ($sortField === 'name') {
            $query->join('users', 'teachers.user_id', '=', 'users.id')
                ->orderBy('users.first_name', $sortDirection)
                ->select('teachers.*');
        } else {
            $query->orderBy($sortField, $sortDirection);
        }

        $perPage = $request->get('per_page', 15);
        $teachers = $query->paginate($perPage);

        return $this->success(TeacherResource::collection($teachers)->response()->getData(true));
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreTeacherRequest $request): JsonResponse
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

            // Assign teacher role
            $user->assignRole('guru');

            // Handle photo upload
            $photoPath = null;
            if ($request->hasFile('photo')) {
                $photoPath = $request->file('photo')->store('teachers', 'public');
            }

            // Create teacher
            $teacher = Teacher::create([
                'user_id' => $user->id,
                'nip' => $data['nip'] ?? null,
                'nuptk' => $data['nuptk'] ?? null,
                'gender' => $data['gender'],
                'birth_place' => $data['birth_place'] ?? null,
                'birth_date' => $data['birth_date'] ?? null,
                'religion' => $data['religion'] ?? null,
                'address' => $data['address'] ?? null,
                'phone' => $data['phone'] ?? null,
                'education_level' => $data['education_level'] ?? null,
                'education_major' => $data['education_major'] ?? null,
                'employment_status' => 'active',
                'join_date' => $data['join_date'] ?? now(),
                'position' => $data['position'] ?? null,
                'specialization' => $data['specialization'] ?? null,
                'photo' => $photoPath,
            ]);

            // Attach subjects
            if (!empty($data['subject_ids'])) {
                $teacher->subjects()->attach($data['subject_ids']);
            }

            DB::commit();

            $teacher->load(['user', 'subjects']);

            return $this->success(
                new TeacherResource($teacher),
                'Guru berhasil ditambahkan',
                201
            );
        } catch (\Exception $e) {
            DB::rollBack();

            return $this->error('Gagal menambahkan guru: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(Teacher $teacher): JsonResponse
    {
        $teacher->load(['user', 'subjects', 'classRooms']);

        return $this->success(new TeacherResource($teacher));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateTeacherRequest $request, Teacher $teacher): JsonResponse
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
                $teacher->user->update($userData);
            }

            // Handle photo upload
            if ($request->hasFile('photo')) {
                if ($teacher->photo) {
                    Storage::disk('public')->delete($teacher->photo);
                }
                $data['photo'] = $request->file('photo')->store('teachers', 'public');
            }

            // Remove user-related fields
            unset($data['first_name'], $data['last_name'], $data['email'], $data['username'], $data['password']);

            // Handle subjects sync
            if (isset($data['subject_ids'])) {
                $teacher->subjects()->sync($data['subject_ids']);
                unset($data['subject_ids']);
            }

            // Update teacher
            $teacher->update($data);

            DB::commit();

            $teacher->load(['user', 'subjects', 'classRooms']);

            return $this->success(new TeacherResource($teacher), 'Guru berhasil diperbarui');
        } catch (\Exception $e) {
            DB::rollBack();

            return $this->error('Gagal memperbarui guru: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Teacher $teacher): JsonResponse
    {
        try {
            DB::beginTransaction();

            if ($teacher->photo) {
                Storage::disk('public')->delete($teacher->photo);
            }

            $teacher->subjects()->detach();
            $teacher->user->delete();
            $teacher->delete();

            DB::commit();

            return $this->success(null, 'Guru berhasil dihapus');
        } catch (\Exception $e) {
            DB::rollBack();

            return $this->error('Gagal menghapus guru: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Get teacher schedules.
     */
    public function schedules(Teacher $teacher): JsonResponse
    {
        // TODO: Implement schedules
        return $this->success([]);
    }

    /**
     * Get teacher attendance.
     */
    public function attendance(Teacher $teacher): JsonResponse
    {
        // TODO: Implement attendance
        return $this->success([]);
    }
}
