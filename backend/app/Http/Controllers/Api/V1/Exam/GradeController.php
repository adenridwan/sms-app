<?php

namespace App\Http\Controllers\Api\V1\Exam;

use App\Http\Controllers\Api\V1\ApiController;
use App\Infrastructure\Persistence\Eloquent\Academic\Classroom;
use App\Infrastructure\Persistence\Eloquent\Exam\ExamScore;
use App\Infrastructure\Persistence\Eloquent\Exam\FinalGrade;
use App\Infrastructure\Persistence\Eloquent\Exam\StudentGrade;
use App\Infrastructure\Persistence\Eloquent\Student\Student;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class GradeController extends ApiController
{
    /**
     * List all grades with filters
     */
    public function index(Request $request): JsonResponse
    {
        abort_unless($request->user()->can('grades.view'), 403);

        $query = FinalGrade::query()
            ->with(['student.user', 'subject', 'semester']);

        if ($request->filled('semester_id')) {
            $query->forSemester($request->semester_id);
        }

        if ($request->filled('subject_id')) {
            $query->forSubject($request->subject_id);
        }

        if ($request->filled('student_id')) {
            $query->forStudent($request->student_id);
        }

        if ($request->filled('status')) {
            if ($request->status === 'approved') {
                $query->approved();
            } elseif ($request->status === 'pending') {
                $query->pending();
            }
        }

        $perPage = $request->integer('per_page', 15);
        $grades = $query->orderBy('created_at', 'desc')->paginate($perPage);

        return $this->collection($grades);
    }

    /**
     * Get grades for a specific student
     */
    public function byStudent(Request $request, Student $student): JsonResponse
    {
        abort_unless($request->user()->can('grades.view'), 403);

        $query = FinalGrade::query()
            ->with(['subject', 'semester', 'approvedBy'])
            ->forStudent($student->id);

        if ($request->filled('semester_id')) {
            $query->forSemester($request->semester_id);
        }

        $grades = $query->orderBy('created_at', 'desc')->get();

        // Group by semester
        $grouped = $grades->groupBy('semester_id')->map(function ($semesterGrades) {
            $semester = $semesterGrades->first()->semester;
            return [
                'semester' => [
                    'id' => $semester->id,
                    'name' => $semester->name,
                    'semester_number' => $semester->semester_number,
                ],
                'grades' => $semesterGrades->map(fn($g) => [
                    'id' => $g->id,
                    'subject' => [
                        'id' => $g->subject->id,
                        'name' => $g->subject->name,
                        'code' => $g->subject->code,
                    ],
                    'knowledge_score' => (float) $g->knowledge_score,
                    'skill_score' => (float) $g->skill_score,
                    'attitude_score' => (float) $g->attitude_score,
                    'final_score' => (float) $g->final_score,
                    'grade_letter' => $g->grade_letter,
                    'predicate' => $g->predicate,
                    'is_passed' => $g->is_passed,
                    'is_approved' => $g->is_approved,
                ]),
                'summary' => [
                    'total_subjects' => $semesterGrades->count(),
                    'passed' => $semesterGrades->where('is_passed', true)->count(),
                    'failed' => $semesterGrades->where('is_passed', false)->count(),
                    'average' => round($semesterGrades->avg('final_score'), 2),
                ],
            ];
        })->values();

        return $this->success([
            'student' => [
                'id' => $student->id,
                'nis' => $student->nis,
                'full_name' => $student->user?->full_name,
            ],
            'semesters' => $grouped,
        ]);
    }

    /**
     * Get grades for a specific classroom
     */
    public function byClassroom(Request $request, Classroom $classroom): JsonResponse
    {
        abort_unless($request->user()->can('grades.view'), 403);

        $request->validate([
            'semester_id' => ['required', 'uuid', 'exists:semesters,id'],
            'subject_id' => ['nullable', 'uuid', 'exists:subjects,id'],
        ]);

        // Get students in classroom
        $studentIds = $classroom->students()->pluck('students.id');

        $query = FinalGrade::query()
            ->with(['student.user', 'subject'])
            ->whereIn('student_id', $studentIds)
            ->forSemester($request->semester_id);

        if ($request->filled('subject_id')) {
            $query->forSubject($request->subject_id);
        }

        $grades = $query->get();

        // Group by student
        $grouped = $grades->groupBy('student_id')->map(function ($studentGrades) {
            $student = $studentGrades->first()->student;
            return [
                'student' => [
                    'id' => $student->id,
                    'nis' => $student->nis,
                    'full_name' => $student->user?->full_name,
                ],
                'grades' => $studentGrades->map(fn($g) => [
                    'id' => $g->id,
                    'subject_id' => $g->subject_id,
                    'subject_name' => $g->subject->name,
                    'final_score' => (float) $g->final_score,
                    'grade_letter' => $g->grade_letter,
                    'is_passed' => $g->is_passed,
                ]),
                'average' => round($studentGrades->avg('final_score'), 2),
            ];
        })->values();

        return $this->success([
            'classroom' => [
                'id' => $classroom->id,
                'name' => $classroom->name,
            ],
            'students' => $grouped,
            'summary' => [
                'total_students' => $grouped->count(),
                'class_average' => $grouped->count() > 0 ? round($grouped->avg('average'), 2) : null,
            ],
        ]);
    }

    /**
     * Finalize grades for a semester/subject
     */
    public function finalize(Request $request): JsonResponse
    {
        abort_unless($request->user()->can('grades.finalize'), 403);

        $validated = $request->validate([
            'semester_id' => ['required', 'uuid', 'exists:semesters,id'],
            'subject_id' => ['required', 'uuid', 'exists:subjects,id'],
            'classroom_id' => ['required', 'uuid', 'exists:classrooms,id'],
        ]);

        // Get all students in the classroom
        $classroom = Classroom::findOrFail($validated['classroom_id']);
        $students = $classroom->students()->get();

        if ($students->isEmpty()) {
            return $this->error('Tidak ada siswa di kelas ini', 422);
        }

        $results = [];
        $errors = [];

        DB::beginTransaction();
        try {
            foreach ($students as $student) {
                // Calculate average from exam scores for this subject/semester
                $examScores = ExamScore::whereHas('exam', function ($q) use ($validated) {
                    $q->where('semester_id', $validated['semester_id'])
                      ->where('subject_id', $validated['subject_id']);
                })
                ->where('student_id', $student->id)
                ->where('is_absent', false)
                ->get();

                if ($examScores->isEmpty()) {
                    $errors[] = [
                        'student_id' => $student->id,
                        'student_name' => $student->user?->full_name,
                        'error' => 'Tidak ada nilai ujian',
                    ];
                    continue;
                }

                // Calculate weighted average
                $totalWeight = 0;
                $weightedSum = 0;

                foreach ($examScores as $examScore) {
                    $weight = $examScore->exam->weight ?? 100;
                    $normalizedScore = ($examScore->score / $examScore->exam->max_score) * 100;
                    $weightedSum += $normalizedScore * $weight;
                    $totalWeight += $weight;
                }

                $finalScore = $totalWeight > 0 ? round($weightedSum / $totalWeight, 2) : 0;
                $gradeLetter = StudentGrade::calculateGradeLetter($finalScore);
                $predicate = StudentGrade::calculatePredicate($finalScore);

                // Create or update final grade
                $finalGrade = FinalGrade::updateOrCreate(
                    [
                        'tenant_id' => tenant()->id,
                        'student_id' => $student->id,
                        'subject_id' => $validated['subject_id'],
                        'semester_id' => $validated['semester_id'],
                    ],
                    [
                        'knowledge_score' => $finalScore, // Simplified - use same for now
                        'skill_score' => $finalScore,
                        'attitude_score' => 85, // Default attitude score
                        'final_score' => $finalScore,
                        'grade_letter' => $gradeLetter,
                        'predicate' => $predicate,
                        'is_passed' => $finalScore >= 70, // KKM default 70
                    ]
                );

                $results[] = $finalGrade;
            }

            DB::commit();

            return $this->success([
                'processed' => count($results),
                'errors' => count($errors),
                'error_details' => $errors,
            ], 'Nilai berhasil difinalisasi');

        } catch (\Exception $e) {
            DB::rollBack();
            return $this->error('Gagal memfinalisasi nilai: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Approve final grades
     */
    public function approve(Request $request): JsonResponse
    {
        abort_unless($request->user()->can('grades.approve'), 403);

        $validated = $request->validate([
            'grade_ids' => ['required', 'array', 'min:1'],
            'grade_ids.*' => ['uuid', 'exists:final_grades,id'],
        ]);

        $grades = FinalGrade::whereIn('id', $validated['grade_ids'])->get();

        foreach ($grades as $grade) {
            $grade->approve($request->user());
        }

        return $this->success([
            'approved' => $grades->count(),
        ], 'Nilai berhasil disetujui');
    }
}
