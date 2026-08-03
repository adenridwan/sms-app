<?php

namespace App\Http\Controllers\Api\V1\Academic;

use App\Exports\Academic\ClassroomsExport;
use App\Exports\Academic\ClassroomsTemplateExport;
use App\Http\Controllers\Api\ApiController;
use App\Http\Resources\Academic\ClassroomResource;
use App\Http\Resources\Academic\ScheduleResource;
use App\Http\Resources\StudentResource;
use App\Imports\Academic\ClassroomsImport;
use App\Infrastructure\Persistence\Eloquent\Academic\AcademicYear;
use App\Infrastructure\Persistence\Eloquent\Academic\Classroom;
use App\Infrastructure\Persistence\Eloquent\Academic\Schedule;
use App\Infrastructure\Persistence\Eloquent\Academic\Semester;
use App\Infrastructure\Persistence\Eloquent\Auth\User;
use App\Infrastructure\Persistence\Eloquent\Teacher\Teacher;
use App\Infrastructure\Persistence\Eloquent\Teacher\TeacherClassroom;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ClassroomController extends ApiController
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request): JsonResponse
    {
        $query = Classroom::with(['academicYear', 'gradeLevel', 'major', 'homeroomTeacher.profile'])
            ->withCount('enrollments')
            ->when($request->search, fn ($q, $search) => $q->where(function ($q) use ($search) {
                $q->where('name', 'ilike', "%{$search}%")
                    ->orWhere('code', 'ilike', "%{$search}%");
            }))
            ->when($request->grade_level_id, fn ($q, $id) => $q->where('grade_level_id', $id))
            ->when($request->major_id, fn ($q, $id) => $q->where('major_id', $id))
            ->when($request->academic_year_id, fn ($q, $id) => $q->where('academic_year_id', $id))
            ->when($request->has('is_active'), fn ($q) => $q->where('is_active', $request->boolean('is_active')));

        // `?mine=1` — hanya kelas yang diampu pemanggil. Dipakai pemilih kelas
        // di aplikasi mobile; peran ber-akses penuh tetap melihat semuanya.
        if ($request->boolean('mine')) {
            $user = $request->user();
            $roles = $user->getRoleNames();

            if ($roles->intersect(\App\Infrastructure\Persistence\Eloquent\Student\Student::ALL_ACCESS_ROLES)->isEmpty()) {
                $ids = $user->teachingClassroomIds();
                $query->whereIn('id', $ids === [] ? ['-'] : $ids);
            }
        }

        // Default urut nama: kolom kode tidak lagi ditampilkan di tabel Kelas,
        // jadi urutan berdasarkan kode akan terbaca acak oleh pengguna.
        $sortField = in_array($request->get('sort'), ['name', 'code', 'capacity', 'created_at'], true)
            ? $request->get('sort')
            : 'name';
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

        $tenantId = $this->currentTenantId($request);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'academic_year_id' => ['required', 'uuid', 'exists:academic_years,id'],
            'grade_level_id' => ['required', 'uuid', 'exists:grade_levels,id'],
            'major_id' => ['nullable', 'uuid', 'exists:majors,id'],
            'homeroom_teacher_id' => [
                'nullable', 'uuid', 'exists:users,id',
                Rule::unique('classrooms', 'homeroom_teacher_id')
                    ->where(fn ($q) => $q->where('tenant_id', $tenantId)
                        ->where('academic_year_id', $request->input('academic_year_id'))),
            ],
            'room' => ['nullable', 'string', 'max:50'],
            'capacity' => ['required', 'integer', 'min:1', 'max:100'],
            'is_active' => ['boolean'],
        ], [
            'homeroom_teacher_id.unique' => 'Guru ini sudah menjadi wali kelas lain pada tahun ajaran yang sama.',
        ]);

        // Kode diisi otomatis (increment per tahun ajaran) agar pengguna
        // tidak perlu mengetik kode manual, yang sering memicu error duplikat.
        $data['code'] = $this->generateNextCode($data['academic_year_id']);

        $classroom = Classroom::create($data);
        $classroom->load(['academicYear', 'gradeLevel', 'major']);

        return $this->success(new ClassroomResource($classroom), 'Kelas berhasil ditambahkan', 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(Classroom $classroom): JsonResponse
    {
        $classroom->load(['academicYear', 'gradeLevel', 'major', 'homeroomTeacher.profile']);
        $classroom->loadCount('enrollments');

        return $this->success(new ClassroomResource($classroom));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Classroom $classroom): JsonResponse
    {
        $effectiveYearId = $request->input('academic_year_id', $classroom->academic_year_id);

        $data = $request->validate([
            'name' => ['sometimes', 'string', 'max:100'],
            'academic_year_id' => ['sometimes', 'uuid', 'exists:academic_years,id'],
            'grade_level_id' => ['sometimes', 'uuid', 'exists:grade_levels,id'],
            'major_id' => ['nullable', 'uuid', 'exists:majors,id'],
            'homeroom_teacher_id' => [
                'nullable', 'uuid', 'exists:users,id',
                Rule::unique('classrooms', 'homeroom_teacher_id')
                    ->where(fn ($q) => $q->where('tenant_id', $classroom->tenant_id)
                        ->where('academic_year_id', $effectiveYearId))
                    ->ignore($classroom->id),
            ],
            'room' => ['nullable', 'string', 'max:50'],
            'capacity' => ['sometimes', 'integer', 'min:1', 'max:100'],
            'is_active' => ['boolean'],
        ], [
            'homeroom_teacher_id.unique' => 'Guru ini sudah menjadi wali kelas lain pada tahun ajaran yang sama.',
        ]);

        // Kode bersifat generated dan tidak dapat diedit manual lewat form.
        // Tapi jika kelas dipindah ke tahun ajaran lain dan kode lamanya
        // kebetulan sudah dipakai kelas lain di sana, generate kode baru
        // otomatis supaya constraint unique per tahun ajaran tetap aman.
        if (isset($data['academic_year_id']) && $data['academic_year_id'] !== $classroom->academic_year_id) {
            $codeExists = Classroom::where('academic_year_id', $data['academic_year_id'])
                ->where('code', $classroom->code)
                ->exists();

            if ($codeExists) {
                $data['code'] = $this->generateNextCode($data['academic_year_id']);
            }
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
     * Guru pengampu kelas ini pada tahun ajaran kelas tsb (penempatan
     * manual, sumber ketiga rumus R3 — lihat TeacherClassroom & Fase 1
     * ROLE-ACCESS-PLAN.md). Ditata dari sini, BUKAN dari Data Guru, supaya
     * tabel `teachers` tidak berubah tiap pergantian tahun ajaran
     * (TEACHER-MODULE-PLAN.md §2/§5).
     */
    public function teachers(Classroom $classroom): JsonResponse
    {
        // whereIn(...teachers.user_id) sengaja disaring: baris teacher_classrooms
        // yang teacher_id-nya tidak lagi punya record teachers (mis. akun
        // dengan role guru tapi tak pernah dibuat lewat menu Data Guru) tidak
        // pernah bisa dikelola ulang lewat picker ini (tak pernah muncul di
        // teachersApi.list()), sehingga selalu ikut terkirim balik utuh dan
        // menggagalkan SELURUH penyimpanan dengan "teacher_ids.0 is invalid".
        // Baris semacam itu disaring dari tampilan; syncTeachers() (full
        // replace) otomatis membersihkannya begitu penyimpanan berikutnya
        // berhasil.
        $assigned = TeacherClassroom::with('teacherUser.profile')
            ->where('classroom_id', $classroom->id)
            ->where('academic_year_id', $classroom->academic_year_id)
            ->whereIn('teacher_id', Teacher::query()->select('user_id'))
            ->get()
            ->map(fn (TeacherClassroom $tc) => [
                'id' => $tc->teacher_id,
                'name' => $tc->teacherUser?->full_name,
            ])
            ->values();

        return $this->success($assigned);
    }

    /**
     * Sinkronkan guru pengampu kelas ini (menimpa penuh dengan daftar yang
     * dikirim, discoped ke tahun ajaran kelas ini saja).
     */
    public function syncTeachers(Request $request, Classroom $classroom): JsonResponse
    {
        abort_unless($request->user()->can('classrooms.manage'), 403);

        $data = $request->validate([
            'teacher_ids' => ['present', 'array'],
            'teacher_ids.*' => ['uuid', Rule::exists('teachers', 'user_id')],
        ]);
        $teacherIds = array_unique($data['teacher_ids']);

        DB::transaction(function () use ($classroom, $teacherIds) {
            TeacherClassroom::where('classroom_id', $classroom->id)
                ->where('academic_year_id', $classroom->academic_year_id)
                ->whereNotIn('teacher_id', $teacherIds)
                ->delete();

            foreach ($teacherIds as $teacherId) {
                TeacherClassroom::firstOrCreate([
                    'classroom_id' => $classroom->id,
                    'academic_year_id' => $classroom->academic_year_id,
                    'teacher_id' => $teacherId,
                ], [
                    'tenant_id' => $classroom->tenant_id,
                ]);
            }
        });

        return $this->teachers($classroom);
    }

    /**
     * Get students enrolled in a classroom.
     */
    public function students(Request $request, Classroom $classroom): JsonResponse
    {
        // Tanpa penjagaan ini, guru bisa membaca daftar siswa kelas mana pun di
        // tenant-nya hanya dengan menebak/menyalin UUID kelas — memfilter di
        // klien saja tidak cukup karena endpoint bisa dipanggil langsung.
        if (! self::canAccessClassroom($request->user(), $classroom->id)) {
            return $this->forbidden('Anda tidak memiliki akses ke kelas ini.');
        }

        // `user.profile` ikut dimuat karena StudentResource membacanya; tanpa
        // ini endpoint gagal total saat lazy loading dimatikan.
        $students = $classroom->enrollments()
            ->with('student.user.profile')
            ->get()
            ->pluck('student')
            ->filter()
            ->values();

        return $this->success(StudentResource::collection($students));
    }

    /**
     * Peran ber-akses penuh melihat semua kelas; guru/wali kelas hanya kelas
     * yang benar-benar diampunya. Aturannya sengaja dibuat sama dengan
     * `Student::scopeVisibleTo()` dan `StudentAttendanceController`.
     */
    private static function canAccessClassroom(?User $user, string $classroomId): bool
    {
        if (! $user) {
            return false;
        }

        $roles = $user->getRoleNames();

        if ($roles->intersect(\App\Infrastructure\Persistence\Eloquent\Student\Student::ALL_ACCESS_ROLES)->isNotEmpty()) {
            return true;
        }

        return $roles->intersect(['guru', 'wali_kelas'])->isNotEmpty()
            && in_array($classroomId, $user->teachingClassroomIds(), true);
    }

    /**
     * Get the classroom schedule for a semester (default: semester aktif).
     */
    public function schedule(Request $request, Classroom $classroom): JsonResponse
    {
        $semesterId = $request->get('semester_id')
            ?? Semester::where('tenant_id', $classroom->tenant_id)->where('is_active', true)->value('id');

        if (! $semesterId) {
            return $this->success([], 'Belum ada semester aktif.');
        }

        $schedules = Schedule::with(['subject', 'teacher', 'timeSlot'])
            ->where('classroom_id', $classroom->id)
            ->where('semester_id', $semesterId)
            ->orderBy('day_of_week')
            ->get();

        return $this->success(ScheduleResource::collection($schedules));
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

        $message = 'Import kelas selesai.';
        if ($import->gradeLevelsCreated > 0) {
            $message .= " {$import->gradeLevelsCreated} tingkat kelas baru dibuat otomatis.";
        }

        return $this->success([
            'created' => $import->created,
            'updated' => $import->updated,
            'grade_levels_created' => $import->gradeLevelsCreated,
            'errors' => $import->errors,
        ], $message);
    }

    /**
     * Generate a unique, incrementing code (KLS01, KLS02, ...) scoped to
     * the given academic year, retrying past any gaps left by deleted
     * records. Import tetap punya kode sendiri dari file (lihat
     * ClassroomsImport) — ini hanya untuk kelas yang dibuat lewat form.
     */
    private function generateNextCode(string $academicYearId): string
    {
        $sequence = Classroom::withTrashed()->where('academic_year_id', $academicYearId)->count();

        do {
            $sequence++;
            $code = 'KLS'.str_pad((string) $sequence, 2, '0', STR_PAD_LEFT);
        } while (Classroom::withTrashed()->where('academic_year_id', $academicYearId)->where('code', $code)->exists());

        return $code;
    }
}
