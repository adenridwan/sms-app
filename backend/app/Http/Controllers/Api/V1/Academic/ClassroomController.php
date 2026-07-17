<?php

namespace App\Http\Controllers\Api\V1\Academic;

use App\Exports\Academic\ClassroomsExport;
use App\Exports\Academic\ClassroomsTemplateExport;
use App\Http\Controllers\Api\ApiController;
use App\Http\Resources\Academic\ClassroomResource;
use App\Http\Resources\StudentResource;
use App\Imports\Academic\ClassroomsImport;
use App\Infrastructure\Persistence\Eloquent\Academic\AcademicYear;
use App\Infrastructure\Persistence\Eloquent\Academic\Classroom;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ClassroomController extends ApiController
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request): JsonResponse
    {
        $query = Classroom::with(['academicYear', 'gradeLevel', 'major'])
            ->withCount('enrollments')
            ->when($request->search, fn ($q, $search) => $q->where(function ($q) use ($search) {
                $q->where('name', 'ilike', "%{$search}%")
                    ->orWhere('code', 'ilike', "%{$search}%");
            }))
            ->when($request->grade_level_id, fn ($q, $id) => $q->where('grade_level_id', $id))
            ->when($request->major_id, fn ($q, $id) => $q->where('major_id', $id))
            ->when($request->academic_year_id, fn ($q, $id) => $q->where('academic_year_id', $id))
            ->when($request->has('is_active'), fn ($q) => $q->where('is_active', $request->boolean('is_active')));

        $sortField = in_array($request->get('sort'), ['name', 'code', 'capacity', 'created_at'], true)
            ? $request->get('sort')
            : 'code';
        $sortDirection = $request->get('direction') === 'desc' ? 'desc' : 'asc';
        $query->orderBy($sortField, $sortDirection);

        $perPage = min((int) $request->get('per_page', 15), 100);
        $classrooms = $query->paginate($perPage);

        return $this->success(ClassroomResource::collection($classrooms)->response()->getData(true));
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request): JsonResponse
    {
        if (! $this->currentTenantId($request)) {
            return $this->error('Konteks sekolah (tenant) tidak ditemukan. Pilih sekolah terlebih dahulu.', 422);
        }

        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'code' => ['required', 'string', 'max:50'],
            'academic_year_id' => ['required', 'uuid', 'exists:academic_years,id'],
            'grade_level_id' => ['required', 'uuid', 'exists:grade_levels,id'],
            'major_id' => ['nullable', 'uuid', 'exists:majors,id'],
            'homeroom_teacher_id' => ['nullable', 'uuid', 'exists:users,id'],
            'room' => ['nullable', 'string', 'max:50'],
            'capacity' => ['required', 'integer', 'min:1', 'max:100'],
            'is_active' => ['boolean'],
        ]);

        $codeExists = Classroom::where('academic_year_id', $data['academic_year_id'])
            ->where('code', $data['code'])
            ->exists();

        if ($codeExists) {
            return $this->validationError(['code' => ['Kode kelas sudah digunakan pada tahun ajaran ini.']]);
        }

        $classroom = Classroom::create($data);
        $classroom->load(['academicYear', 'gradeLevel', 'major']);

        return $this->success(new ClassroomResource($classroom), 'Kelas berhasil ditambahkan', 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(Classroom $classroom): JsonResponse
    {
        $classroom->load(['academicYear', 'gradeLevel', 'major', 'homeroomTeacher']);
        $classroom->loadCount('enrollments');

        return $this->success(new ClassroomResource($classroom));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Classroom $classroom): JsonResponse
    {
        $data = $request->validate([
            'name' => ['sometimes', 'string', 'max:100'],
            'code' => ['sometimes', 'string', 'max:50'],
            'academic_year_id' => ['sometimes', 'uuid', 'exists:academic_years,id'],
            'grade_level_id' => ['sometimes', 'uuid', 'exists:grade_levels,id'],
            'major_id' => ['nullable', 'uuid', 'exists:majors,id'],
            'homeroom_teacher_id' => ['nullable', 'uuid', 'exists:users,id'],
            'room' => ['nullable', 'string', 'max:50'],
            'capacity' => ['sometimes', 'integer', 'min:1', 'max:100'],
            'is_active' => ['boolean'],
        ]);

        $code = $data['code'] ?? $classroom->code;
        $yearId = $data['academic_year_id'] ?? $classroom->academic_year_id;

        $codeExists = Classroom::where('academic_year_id', $yearId)
            ->where('code', $code)
            ->where('id', '!=', $classroom->id)
            ->exists();

        if ($codeExists) {
            return $this->validationError(['code' => ['Kode kelas sudah digunakan pada tahun ajaran ini.']]);
        }

        $classroom->update($data);
        $classroom->load(['academicYear', 'gradeLevel', 'major']);

        return $this->success(new ClassroomResource($classroom), 'Kelas berhasil diperbarui');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Classroom $classroom): JsonResponse
    {
        if ($classroom->enrollments()->exists()) {
            return $this->error('Kelas tidak dapat dihapus karena masih memiliki siswa', 422);
        }

        $classroom->delete();

        return $this->success(null, 'Kelas berhasil dihapus');
    }

    /**
     * Get students enrolled in a classroom.
     */
    public function students(Classroom $classroom): JsonResponse
    {
        $students = $classroom->enrollments()
            ->with('student.user')
            ->get()
            ->pluck('student')
            ->filter()
            ->values();

        return $this->success(StudentResource::collection($students));
    }

    /**
     * Get the classroom schedule (not implemented yet).
     */
    public function schedule(Classroom $classroom): JsonResponse
    {
        return $this->success([], 'Jadwal belum tersedia.');
    }

    /**
     * Export classrooms to an Excel file.
     */
    public function export(): BinaryFileResponse
    {
        return Excel::download(new ClassroomsExport(), 'kelas.xlsx');
    }

    /**
     * Download the default import template.
     */
    public function template(): BinaryFileResponse
    {
        return Excel::download(new ClassroomsTemplateExport(), 'template-import-kelas.xlsx');
    }

    /**
     * Import classrooms from an uploaded file (upsert by code within the active academic year).
     */
    public function import(Request $request): JsonResponse
    {
        if (! $this->currentTenantId($request)) {
            return $this->error('Konteks sekolah (tenant) tidak ditemukan. Pilih sekolah terlebih dahulu.', 422);
        }

        $request->validate([
            'file' => ['required', 'file', 'mimes:xlsx,xls,csv', 'max:5120'],
        ]);

        $academicYear = AcademicYear::active()->first();

        if (! $academicYear) {
            return $this->error('Tidak ada tahun ajaran aktif. Buat tahun ajaran aktif terlebih dahulu.', 422);
        }

        $import = new ClassroomsImport($academicYear);
        Excel::import($import, $request->file('file'));

        return $this->success([
            'created' => $import->created,
            'updated' => $import->updated,
            'errors' => $import->errors,
        ], 'Import kelas selesai.');
    }
}
