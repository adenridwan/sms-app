<?php

namespace App\Http\Controllers\Api\V1\MasterData;

use App\Http\Controllers\Api\ApiController;
use App\Http\Resources\ClassRoomResource;
use App\Http\Resources\StudentResource;
use App\Models\MasterData\ClassRoom;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ClassRoomController extends ApiController
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request): JsonResponse
    {
        $query = ClassRoom::with(['academicYear', 'homeroomTeacher.user'])
            ->withCount('students')
            ->when($request->search, fn($q, $search) => $q->where('name', 'like', "%{$search}%"))
            ->when($request->level, fn($q, $level) => $q->where('level', $level))
            ->when($request->major, fn($q, $major) => $q->where('major', $major))
            ->when($request->academic_year_id, fn($q, $yearId) => $q->where('academic_year_id', $yearId))
            ->when($request->has('is_active'), fn($q) => $q->where('is_active', $request->boolean('is_active')));

        $sortField = $request->get('sort', 'name');
        $sortDirection = $request->get('direction', 'asc');
        $query->orderBy($sortField, $sortDirection);

        $perPage = $request->get('per_page', 15);
        $classRooms = $query->paginate($perPage);

        return $this->success(ClassRoomResource::collection($classRooms)->response()->getData(true));
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:50'],
            'level' => ['required', 'string', 'max:20'],
            'major' => ['nullable', 'string', 'max:100'],
            'capacity' => ['required', 'integer', 'min:1', 'max:100'],
            'academic_year_id' => ['required', 'uuid', 'exists:academic_years,id'],
            'homeroom_teacher_id' => ['nullable', 'uuid', 'exists:teachers,id'],
            'is_active' => ['boolean'],
        ]);

        $classRoom = ClassRoom::create($data);
        $classRoom->load(['academicYear', 'homeroomTeacher.user']);

        return $this->success(
            new ClassRoomResource($classRoom),
            'Kelas berhasil ditambahkan',
            201
        );
    }

    /**
     * Display the specified resource.
     */
    public function show(ClassRoom $classRoom): JsonResponse
    {
        $classRoom->load(['academicYear', 'homeroomTeacher.user']);
        $classRoom->loadCount('students');

        return $this->success(new ClassRoomResource($classRoom));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, ClassRoom $classRoom): JsonResponse
    {
        $data = $request->validate([
            'name' => ['sometimes', 'string', 'max:50'],
            'level' => ['sometimes', 'string', 'max:20'],
            'major' => ['nullable', 'string', 'max:100'],
            'capacity' => ['sometimes', 'integer', 'min:1', 'max:100'],
            'academic_year_id' => ['sometimes', 'uuid', 'exists:academic_years,id'],
            'homeroom_teacher_id' => ['nullable', 'uuid', 'exists:teachers,id'],
            'is_active' => ['boolean'],
        ]);

        $classRoom->update($data);
        $classRoom->load(['academicYear', 'homeroomTeacher.user']);

        return $this->success(new ClassRoomResource($classRoom), 'Kelas berhasil diperbarui');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(ClassRoom $classRoom): JsonResponse
    {
        if ($classRoom->students()->exists()) {
            return $this->error('Kelas tidak dapat dihapus karena masih memiliki siswa', 422);
        }

        $classRoom->delete();

        return $this->success(null, 'Kelas berhasil dihapus');
    }

    /**
     * Get students in a class.
     */
    public function students(ClassRoom $classRoom): JsonResponse
    {
        $students = $classRoom->students()
            ->with('user')
            ->orderBy('students.nis')
            ->get();

        return $this->success(StudentResource::collection($students));
    }
}
