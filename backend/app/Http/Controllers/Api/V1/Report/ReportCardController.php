<?php

namespace App\Http\Controllers\Api\V1\Report;

use App\Http\Controllers\Api\V1\ApiController;
use App\Http\Resources\ReportCardResource;
use App\Infrastructure\Persistence\Eloquent\Academic\Classroom;
use App\Infrastructure\Persistence\Eloquent\Exam\FinalGrade;
use App\Infrastructure\Persistence\Eloquent\Report\ReportCard;
use App\Infrastructure\Persistence\Eloquent\Report\ReportCardCharacter;
use App\Infrastructure\Persistence\Eloquent\Report\ReportCardExtracurricular;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class ReportCardController extends ApiController
{
    /**
     * List report cards with filters
     */
    public function index(Request $request): JsonResponse
    {
        abort_unless($request->user()->can('report-cards.view'), 403);

        $query = ReportCard::query()
            ->with(['student.user', 'classroom', 'semester', 'academicYear'])
            ->search($request->search);

        if ($request->filled('academic_year_id')) {
            $query->forAcademicYear($request->academic_year_id);
        }

        if ($request->filled('semester_id')) {
            $query->forSemester($request->semester_id);
        }

        if ($request->filled('classroom_id')) {
            $query->forClassroom($request->classroom_id);
        }

        if ($request->filled('student_id')) {
            $query->forStudent($request->student_id);
        }

        if ($request->filled('status')) {
            $query->byStatus($request->status);
        }

        $perPage = $request->integer('per_page', 15);
        $reportCards = $query->orderBy('created_at', 'desc')->paginate($perPage);

        return $this->collection($reportCards, ReportCardResource::class);
    }

    /**
     * Get single report card with all details
     */
    public function show(Request $request, ReportCard $reportCard): JsonResponse
    {
        abort_unless($request->user()->can('report-cards.view'), 403);

        $reportCard->load([
            'student.user',
            'classroom',
            'semester',
            'academicYear',
            'homeroomTeacher',
            'principal',
            'extracurriculars',
            'characters',
        ]);

        // Also get the grades
        $grades = FinalGrade::with(['subject'])
            ->where('student_id', $reportCard->student_id)
            ->where('semester_id', $reportCard->semester_id)
            ->orderBy('created_at')
            ->get();

        $gradesData = $grades->map(fn($g) => [
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
        ]);

        $data = (new ReportCardResource($reportCard))->toArray($request);
        $data['grades'] = $gradesData;

        return $this->success($data);
    }

    /**
     * Generate report cards for a classroom/semester
     */
    public function generate(Request $request): JsonResponse
    {
        abort_unless($request->user()->can('report-cards.generate'), 403);

        $validated = $request->validate([
            'academic_year_id' => ['required', 'uuid', 'exists:academic_years,id'],
            'semester_id' => ['required', 'uuid', 'exists:semesters,id'],
            'classroom_id' => ['required', 'uuid', 'exists:classrooms,id'],
        ]);

        $classroom = Classroom::with('students')->findOrFail($validated['classroom_id']);
        $students = $classroom->students;

        if ($students->isEmpty()) {
            return $this->error('Tidak ada siswa di kelas ini', 422);
        }

        $created = 0;
        $skipped = 0;
        $errors = [];

        DB::beginTransaction();
        try {
            foreach ($students as $student) {
                // Check if report card already exists
                $existing = ReportCard::where('student_id', $student->id)
                    ->where('semester_id', $validated['semester_id'])
                    ->first();

                if ($existing) {
                    $skipped++;
                    continue;
                }

                // Check if student has final grades
                $gradesCount = FinalGrade::where('student_id', $student->id)
                    ->where('semester_id', $validated['semester_id'])
                    ->count();

                if ($gradesCount === 0) {
                    $errors[] = [
                        'student_id' => $student->id,
                        'student_name' => $student->user?->full_name,
                        'error' => 'Belum ada nilai final',
                    ];
                    continue;
                }

                // Create report card
                $reportCard = ReportCard::create([
                    'tenant_id' => tenant()->id,
                    'student_id' => $student->id,
                    'classroom_id' => $validated['classroom_id'],
                    'academic_year_id' => $validated['academic_year_id'],
                    'semester_id' => $validated['semester_id'],
                    'status' => ReportCard::STATUS_DRAFT,
                    'promotion_status' => ReportCard::PROMOTION_PENDING,
                ]);

                // Calculate statistics
                $reportCard->calculateStatistics();

                // Add default character assessments
                foreach (ReportCardCharacter::CHARACTERS as $key => $name) {
                    ReportCardCharacter::create([
                        'report_card_id' => $reportCard->id,
                        'character_name' => $name,
                        'predicate' => 'Baik', // Default
                    ]);
                }

                $created++;
            }

            // Calculate ranks for all created report cards
            $allReportCards = ReportCard::where('classroom_id', $validated['classroom_id'])
                ->where('semester_id', $validated['semester_id'])
                ->get();

            foreach ($allReportCards as $rc) {
                $rc->calculateRank();
            }

            DB::commit();

            return $this->success([
                'created' => $created,
                'skipped' => $skipped,
                'errors' => count($errors),
                'error_details' => $errors,
            ], 'Rapor berhasil digenerate');

        } catch (\Exception $e) {
            DB::rollBack();
            return $this->error('Gagal generate rapor: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Update a report card
     */
    public function update(Request $request, ReportCard $reportCard): JsonResponse
    {
        abort_unless($request->user()->can('report-cards.manage'), 403);

        if (!$reportCard->canBeEdited()) {
            return $this->error('Rapor tidak dapat diubah karena sudah disetujui', 422);
        }

        $validated = $request->validate([
            'homeroom_notes' => ['nullable', 'string', 'max:2000'],
            'principal_notes' => ['nullable', 'string', 'max:2000'],
            'promotion_status' => ['nullable', Rule::in(array_keys(ReportCard::PROMOTION_STATUSES))],
            'next_classroom' => ['nullable', 'string', 'max:100'],
            'total_present_days' => ['nullable', 'integer', 'min:0'],
            'total_absent_days' => ['nullable', 'integer', 'min:0'],
            'total_sick_days' => ['nullable', 'integer', 'min:0'],
            'total_permitted_days' => ['nullable', 'integer', 'min:0'],
            'extracurriculars' => ['nullable', 'array'],
            'extracurriculars.*.activity_name' => ['required', 'string', 'max:200'],
            'extracurriculars.*.predicate' => ['nullable', 'string', 'max:50'],
            'extracurriculars.*.description' => ['nullable', 'string', 'max:500'],
            'characters' => ['nullable', 'array'],
            'characters.*.character_name' => ['required', 'string', 'max:100'],
            'characters.*.predicate' => ['required', 'string', 'max:50'],
            'characters.*.description' => ['nullable', 'string', 'max:500'],
        ]);

        DB::beginTransaction();
        try {
            // Update main fields
            $reportCard->update([
                'homeroom_notes' => $validated['homeroom_notes'] ?? $reportCard->homeroom_notes,
                'principal_notes' => $validated['principal_notes'] ?? $reportCard->principal_notes,
                'promotion_status' => $validated['promotion_status'] ?? $reportCard->promotion_status,
                'next_classroom' => $validated['next_classroom'] ?? $reportCard->next_classroom,
                'total_present_days' => $validated['total_present_days'] ?? $reportCard->total_present_days,
                'total_absent_days' => $validated['total_absent_days'] ?? $reportCard->total_absent_days,
                'total_sick_days' => $validated['total_sick_days'] ?? $reportCard->total_sick_days,
                'total_permitted_days' => $validated['total_permitted_days'] ?? $reportCard->total_permitted_days,
            ]);

            // Update extracurriculars if provided
            if (isset($validated['extracurriculars'])) {
                $reportCard->extracurriculars()->delete();
                foreach ($validated['extracurriculars'] as $extra) {
                    ReportCardExtracurricular::create([
                        'report_card_id' => $reportCard->id,
                        'activity_name' => $extra['activity_name'],
                        'predicate' => $extra['predicate'] ?? null,
                        'description' => $extra['description'] ?? null,
                    ]);
                }
            }

            // Update characters if provided
            if (isset($validated['characters'])) {
                $reportCard->characters()->delete();
                foreach ($validated['characters'] as $char) {
                    ReportCardCharacter::create([
                        'report_card_id' => $reportCard->id,
                        'character_name' => $char['character_name'],
                        'predicate' => $char['predicate'],
                        'description' => $char['description'] ?? null,
                    ]);
                }
            }

            DB::commit();

            $reportCard->load([
                'student.user',
                'classroom',
                'semester',
                'academicYear',
                'extracurriculars',
                'characters',
            ]);

            return $this->success(
                new ReportCardResource($reportCard),
                'Rapor berhasil diperbarui'
            );

        } catch (\Exception $e) {
            DB::rollBack();
            return $this->error('Gagal memperbarui rapor: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Submit for review
     */
    public function submitForReview(Request $request, ReportCard $reportCard): JsonResponse
    {
        abort_unless($request->user()->can('report-cards.manage'), 403);

        if ($reportCard->status !== ReportCard::STATUS_DRAFT) {
            return $this->error('Hanya rapor draf yang dapat diajukan untuk review', 422);
        }

        $reportCard->status = ReportCard::STATUS_REVIEWED;
        $reportCard->homeroom_teacher_id = $request->user()->id;
        $reportCard->save();

        return $this->success(
            new ReportCardResource($reportCard->load(['student.user', 'classroom', 'semester'])),
            'Rapor berhasil diajukan untuk review'
        );
    }

    /**
     * Approve report card
     */
    public function approve(Request $request, ReportCard $reportCard): JsonResponse
    {
        abort_unless($request->user()->can('report-cards.approve'), 403);

        if (!$reportCard->canBeApproved()) {
            return $this->error('Rapor tidak dapat disetujui. Pastikan sudah dalam status review.', 422);
        }

        $reportCard->approve($request->user());

        return $this->success(
            new ReportCardResource($reportCard->load(['student.user', 'classroom', 'semester', 'principal'])),
            'Rapor berhasil disetujui'
        );
    }

    /**
     * Publish report card
     */
    public function publish(Request $request, ReportCard $reportCard): JsonResponse
    {
        abort_unless($request->user()->can('report-cards.publish'), 403);

        if (!$reportCard->canBePublished()) {
            return $this->error('Rapor tidak dapat dipublikasikan. Pastikan sudah disetujui.', 422);
        }

        $reportCard->publish();

        return $this->success(
            new ReportCardResource($reportCard->load(['student.user', 'classroom', 'semester'])),
            'Rapor berhasil dipublikasikan'
        );
    }

    /**
     * Bulk approve report cards
     */
    public function bulkApprove(Request $request): JsonResponse
    {
        abort_unless($request->user()->can('report-cards.approve'), 403);

        $validated = $request->validate([
            'report_card_ids' => ['required', 'array', 'min:1'],
            'report_card_ids.*' => ['uuid', 'exists:report_cards,id'],
        ]);

        $approved = 0;
        $skipped = 0;

        foreach ($validated['report_card_ids'] as $id) {
            $reportCard = ReportCard::find($id);
            if ($reportCard && $reportCard->canBeApproved()) {
                $reportCard->approve($request->user());
                $approved++;
            } else {
                $skipped++;
            }
        }

        return $this->success([
            'approved' => $approved,
            'skipped' => $skipped,
        ], "{$approved} rapor berhasil disetujui");
    }

    /**
     * Bulk publish report cards
     */
    public function bulkPublish(Request $request): JsonResponse
    {
        abort_unless($request->user()->can('report-cards.publish'), 403);

        $validated = $request->validate([
            'report_card_ids' => ['required', 'array', 'min:1'],
            'report_card_ids.*' => ['uuid', 'exists:report_cards,id'],
        ]);

        $published = 0;
        $skipped = 0;

        foreach ($validated['report_card_ids'] as $id) {
            $reportCard = ReportCard::find($id);
            if ($reportCard && $reportCard->canBePublished()) {
                $reportCard->publish();
                $published++;
            } else {
                $skipped++;
            }
        }

        return $this->success([
            'published' => $published,
            'skipped' => $skipped,
        ], "{$published} rapor berhasil dipublikasikan");
    }

    /**
     * Get PDF of report card (placeholder - would need PDF generation library)
     */
    public function pdf(Request $request, ReportCard $reportCard): JsonResponse
    {
        abort_unless($request->user()->can('report-cards.view'), 403);

        // For now, return the data that would be used for PDF generation
        // In a real implementation, this would generate and return a PDF file

        $reportCard->load([
            'student.user',
            'classroom',
            'semester',
            'academicYear',
            'homeroomTeacher',
            'principal',
            'extracurriculars',
            'characters',
        ]);

        $grades = FinalGrade::with(['subject'])
            ->where('student_id', $reportCard->student_id)
            ->where('semester_id', $reportCard->semester_id)
            ->orderBy('created_at')
            ->get();

        return $this->success([
            'message' => 'PDF generation not yet implemented',
            'report_card' => new ReportCardResource($reportCard),
            'grades' => $grades->map(fn($g) => [
                'subject' => $g->subject->name,
                'knowledge_score' => $g->knowledge_score,
                'skill_score' => $g->skill_score,
                'final_score' => $g->final_score,
                'grade_letter' => $g->grade_letter,
                'predicate' => $g->predicate,
            ]),
        ]);
    }

    /**
     * Get statistics
     */
    public function statistics(Request $request): JsonResponse
    {
        abort_unless($request->user()->can('report-cards.view'), 403);

        $query = ReportCard::query();

        if ($request->filled('academic_year_id')) {
            $query->forAcademicYear($request->academic_year_id);
        }

        if ($request->filled('semester_id')) {
            $query->forSemester($request->semester_id);
        }

        $total = (clone $query)->count();
        $byStatus = (clone $query)->selectRaw('status, count(*) as count')
            ->groupBy('status')
            ->pluck('count', 'status')
            ->toArray();

        $byPromotion = (clone $query)->selectRaw('promotion_status, count(*) as count')
            ->groupBy('promotion_status')
            ->pluck('count', 'promotion_status')
            ->toArray();

        return $this->success([
            'total' => $total,
            'by_status' => $byStatus,
            'by_promotion' => $byPromotion,
        ]);
    }
}
