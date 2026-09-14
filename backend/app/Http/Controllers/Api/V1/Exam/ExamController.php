<?php

namespace App\Http\Controllers\Api\V1\Exam;

use App\Http\Controllers\Api\V1\ApiController;
use App\Http\Resources\ExamResource;
use App\Http\Resources\ExamScoreResource;
use App\Infrastructure\Persistence\Eloquent\Exam\Exam;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ExamController extends ApiController
{
    public function index(Request $request): JsonResponse
    {
        abort_unless($request->user()->can('exams.view'), 403);

        $query = Exam::query()
            ->with(['academicYear', 'semester', 'subject', 'examType', 'classroom', 'teacher.user'])
            ->search($request->search);

        // Filter by academic year
        if ($request->filled('academic_year_id')) {
            $query->forAcademicYear($request->academic_year_id);
        }

        // Filter by semester
        if ($request->filled('semester_id')) {
            $query->forSemester($request->semester_id);
        }

        // Filter by subject
        if ($request->filled('subject_id')) {
            $query->forSubject($request->subject_id);
        }

        // Filter by classroom
        if ($request->filled('classroom_id')) {
            $query->forClassroom($request->classroom_id);
        }

        // Filter by teacher (for teacher's own exams)
        if ($request->filled('teacher_id')) {
            $query->forTeacher($request->teacher_id);
        }

        // Filter by exam type
        if ($request->filled('exam_type_id')) {
            $query->where('exam_type_id', $request->exam_type_id);
        }

        // Filter by status
        if ($request->filled('status')) {
            $query->byStatus($request->status);
        }

        $perPage = $request->integer('per_page', 15);
        $exams = $query->orderBy('exam_date', 'desc')
            ->orderBy('created_at', 'desc')
            ->paginate($perPage);

        return $this->collection($exams, ExamResource::class);
    }

    public function store(Request $request): JsonResponse
    {
        abort_unless($request->user()->can('exams.manage'), 403);

        $validated = $request->validate([
            'academic_year_id' => ['required', 'uuid', 'exists:academic_years,id'],
            'semester_id' => ['required', 'uuid', 'exists:semesters,id'],
            'subject_id' => ['required', 'uuid', 'exists:subjects,id'],
            'exam_type_id' => ['required', 'uuid', 'exists:exam_types,id'],
            'classroom_id' => ['required', 'uuid', 'exists:classrooms,id'],
            'teacher_id' => ['nullable', 'uuid', 'exists:teachers,id'],
            'name' => ['required', 'string', 'max:200'],
            'description' => ['nullable', 'string', 'max:1000'],
            'exam_date' => ['required', 'date'],
            'start_time' => ['nullable', 'date_format:H:i'],
            'end_time' => ['nullable', 'date_format:H:i', 'after:start_time'],
            'duration_minutes' => ['nullable', 'integer', 'min:1', 'max:480'],
            'max_score' => ['required', 'numeric', 'min:1', 'max:1000'],
            'passing_score' => ['required', 'numeric', 'min:0', 'lte:max_score'],
            'weight' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'status' => ['nullable', Rule::in(array_keys(Exam::STATUSES))],
        ]);

        $validated['tenant_id'] = tenant()->id;
        $validated['status'] = $validated['status'] ?? Exam::STATUS_DRAFT;
        $validated['weight'] = $validated['weight'] ?? 100;

        $exam = Exam::create($validated);
        $exam->load(['academicYear', 'semester', 'subject', 'examType', 'classroom', 'teacher.user']);

        return $this->success(
            new ExamResource($exam),
            'Ujian berhasil ditambahkan',
            201
        );
    }

    public function show(Request $request, Exam $exam): JsonResponse
    {
        abort_unless($request->user()->can('exams.view'), 403);

        $exam->load(['academicYear', 'semester', 'subject', 'examType', 'classroom', 'teacher.user']);

        return $this->success(new ExamResource($exam));
    }

    public function update(Request $request, Exam $exam): JsonResponse
    {
        abort_unless($request->user()->can('exams.manage'), 403);

        if (!$exam->canBeEdited()) {
            return $this->error('Ujian tidak dapat diubah karena sudah berlangsung atau selesai', 422);
        }

        $validated = $request->validate([
            'academic_year_id' => ['sometimes', 'required', 'uuid', 'exists:academic_years,id'],
            'semester_id' => ['sometimes', 'required', 'uuid', 'exists:semesters,id'],
            'subject_id' => ['sometimes', 'required', 'uuid', 'exists:subjects,id'],
            'exam_type_id' => ['sometimes', 'required', 'uuid', 'exists:exam_types,id'],
            'classroom_id' => ['sometimes', 'required', 'uuid', 'exists:classrooms,id'],
            'teacher_id' => ['nullable', 'uuid', 'exists:teachers,id'],
            'name' => ['sometimes', 'required', 'string', 'max:200'],
            'description' => ['nullable', 'string', 'max:1000'],
            'exam_date' => ['sometimes', 'required', 'date'],
            'start_time' => ['nullable', 'date_format:H:i'],
            'end_time' => ['nullable', 'date_format:H:i', 'after:start_time'],
            'duration_minutes' => ['nullable', 'integer', 'min:1', 'max:480'],
            'max_score' => ['sometimes', 'required', 'numeric', 'min:1', 'max:1000'],
            'passing_score' => ['sometimes', 'required', 'numeric', 'min:0'],
            'weight' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'status' => ['nullable', Rule::in(array_keys(Exam::STATUSES))],
        ]);

        // Validate passing_score <= max_score
        $maxScore = $validated['max_score'] ?? $exam->max_score;
        $passingScore = $validated['passing_score'] ?? $exam->passing_score;
        if ($passingScore > $maxScore) {
            return $this->error('Nilai minimal kelulusan tidak boleh lebih dari nilai maksimal', 422);
        }

        $exam->update($validated);
        $exam->load(['academicYear', 'semester', 'subject', 'examType', 'classroom', 'teacher.user']);

        return $this->success(
            new ExamResource($exam),
            'Ujian berhasil diperbarui'
        );
    }

    public function destroy(Request $request, Exam $exam): JsonResponse
    {
        abort_unless($request->user()->can('exams.manage'), 403);

        if (!$exam->canBeDeleted()) {
            return $this->error('Ujian tidak dapat dihapus karena sudah memiliki nilai', 422);
        }

        $exam->delete();

        return $this->success(null, 'Ujian berhasil dihapus');
    }

    /**
     * Get scores for an exam
     */
    public function scores(Request $request, Exam $exam): JsonResponse
    {
        abort_unless($request->user()->can('exams.view'), 403);

        $scores = $exam->scores()
            ->with(['student.user', 'gradedBy'])
            ->orderBy('created_at', 'desc')
            ->get();

        return $this->success(ExamScoreResource::collection($scores));
    }

    /**
     * Get exam statistics
     */
    public function statistics(Request $request): JsonResponse
    {
        abort_unless($request->user()->can('exams.view'), 403);

        $query = Exam::query();

        if ($request->filled('academic_year_id')) {
            $query->forAcademicYear($request->academic_year_id);
        }

        if ($request->filled('semester_id')) {
            $query->forSemester($request->semester_id);
        }

        $totalExams = (clone $query)->count();
        $byStatus = (clone $query)->selectRaw('status, count(*) as count')
            ->groupBy('status')
            ->pluck('count', 'status')
            ->toArray();

        $completedExams = (clone $query)->byStatus(Exam::STATUS_COMPLETED)->get();
        $averageScore = $completedExams->avg('average_score');

        return $this->success([
            'total_exams' => $totalExams,
            'by_status' => $byStatus,
            'average_score' => $averageScore ? round($averageScore, 2) : null,
        ]);
    }
}
