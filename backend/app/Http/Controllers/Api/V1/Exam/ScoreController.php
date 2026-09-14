<?php

namespace App\Http\Controllers\Api\V1\Exam;

use App\Http\Controllers\Api\V1\ApiController;
use App\Http\Resources\ExamScoreResource;
use App\Infrastructure\Persistence\Eloquent\Exam\Exam;
use App\Infrastructure\Persistence\Eloquent\Exam\ExamScore;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ScoreController extends ApiController
{
    /**
     * Store a single score
     */
    public function store(Request $request, Exam $exam): JsonResponse
    {
        abort_unless($request->user()->can('grades.input'), 403);

        if (!$exam->canAcceptScores()) {
            return $this->error('Ujian belum bisa menerima nilai', 422);
        }

        $validated = $request->validate([
            'student_id' => ['required', 'uuid', 'exists:students,id'],
            'score' => ['nullable', 'numeric', 'min:0', 'max:' . $exam->max_score],
            'notes' => ['nullable', 'string', 'max:500'],
            'is_absent' => ['boolean'],
        ]);

        // Check if score already exists
        $existingScore = ExamScore::where('exam_id', $exam->id)
            ->where('student_id', $validated['student_id'])
            ->first();

        if ($existingScore) {
            // Update existing
            $existingScore->update([
                'score' => $validated['is_absent'] ?? false ? null : $validated['score'],
                'notes' => $validated['notes'] ?? null,
                'is_absent' => $validated['is_absent'] ?? false,
                'graded_by' => $request->user()->id,
                'graded_at' => now(),
            ]);

            return $this->success(
                new ExamScoreResource($existingScore->load(['student.user', 'gradedBy'])),
                'Nilai berhasil diperbarui'
            );
        }

        // Create new
        $score = ExamScore::create([
            'tenant_id' => tenant()->id,
            'exam_id' => $exam->id,
            'student_id' => $validated['student_id'],
            'score' => $validated['is_absent'] ?? false ? null : $validated['score'],
            'notes' => $validated['notes'] ?? null,
            'is_absent' => $validated['is_absent'] ?? false,
            'graded_by' => $request->user()->id,
            'graded_at' => now(),
        ]);

        return $this->success(
            new ExamScoreResource($score->load(['student.user', 'gradedBy'])),
            'Nilai berhasil disimpan',
            201
        );
    }

    /**
     * Store multiple scores at once (bulk)
     */
    public function storeBulk(Request $request, Exam $exam): JsonResponse
    {
        abort_unless($request->user()->can('grades.input'), 403);

        if (!$exam->canAcceptScores()) {
            return $this->error('Ujian belum bisa menerima nilai', 422);
        }

        $validated = $request->validate([
            'scores' => ['required', 'array', 'min:1'],
            'scores.*.student_id' => ['required', 'uuid', 'exists:students,id'],
            'scores.*.score' => ['nullable', 'numeric', 'min:0', 'max:' . $exam->max_score],
            'scores.*.notes' => ['nullable', 'string', 'max:500'],
            'scores.*.is_absent' => ['boolean'],
        ]);

        $results = [];
        $errors = [];

        DB::beginTransaction();
        try {
            foreach ($validated['scores'] as $index => $scoreData) {
                $existingScore = ExamScore::where('exam_id', $exam->id)
                    ->where('student_id', $scoreData['student_id'])
                    ->first();

                $isAbsent = $scoreData['is_absent'] ?? false;
                $scoreValue = $isAbsent ? null : ($scoreData['score'] ?? null);

                if ($existingScore) {
                    $existingScore->update([
                        'score' => $scoreValue,
                        'notes' => $scoreData['notes'] ?? null,
                        'is_absent' => $isAbsent,
                        'graded_by' => $request->user()->id,
                        'graded_at' => now(),
                    ]);
                    $results[] = $existingScore;
                } else {
                    $newScore = ExamScore::create([
                        'tenant_id' => tenant()->id,
                        'exam_id' => $exam->id,
                        'student_id' => $scoreData['student_id'],
                        'score' => $scoreValue,
                        'notes' => $scoreData['notes'] ?? null,
                        'is_absent' => $isAbsent,
                        'graded_by' => $request->user()->id,
                        'graded_at' => now(),
                    ]);
                    $results[] = $newScore;
                }
            }

            DB::commit();

            // Load relations for response
            $scoreIds = collect($results)->pluck('id');
            $savedScores = ExamScore::whereIn('id', $scoreIds)
                ->with(['student.user', 'gradedBy'])
                ->get();

            return $this->success([
                'saved' => ExamScoreResource::collection($savedScores),
                'count' => count($results),
            ], 'Nilai berhasil disimpan');

        } catch (\Exception $e) {
            DB::rollBack();
            return $this->error('Gagal menyimpan nilai: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Update a score
     */
    public function update(Request $request, ExamScore $score): JsonResponse
    {
        abort_unless($request->user()->can('grades.input'), 403);

        $exam = $score->exam;
        if (!$exam->canAcceptScores()) {
            return $this->error('Ujian sudah tidak bisa diubah nilainya', 422);
        }

        $validated = $request->validate([
            'score' => ['nullable', 'numeric', 'min:0', 'max:' . $exam->max_score],
            'notes' => ['nullable', 'string', 'max:500'],
            'is_absent' => ['boolean'],
        ]);

        $isAbsent = $validated['is_absent'] ?? $score->is_absent;

        $score->update([
            'score' => $isAbsent ? null : ($validated['score'] ?? $score->score),
            'notes' => $validated['notes'] ?? $score->notes,
            'is_absent' => $isAbsent,
            'graded_by' => $request->user()->id,
            'graded_at' => now(),
        ]);

        return $this->success(
            new ExamScoreResource($score->load(['student.user', 'gradedBy', 'exam'])),
            'Nilai berhasil diperbarui'
        );
    }

    /**
     * Delete a score
     */
    public function destroy(Request $request, ExamScore $score): JsonResponse
    {
        abort_unless($request->user()->can('grades.input'), 403);

        $exam = $score->exam;
        if (!$exam->canAcceptScores()) {
            return $this->error('Nilai ujian sudah tidak bisa dihapus', 422);
        }

        $score->delete();

        return $this->success(null, 'Nilai berhasil dihapus');
    }
}
