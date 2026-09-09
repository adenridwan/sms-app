<?php

namespace App\Http\Controllers\Api\V1\Finance;

use App\Http\Controllers\Api\ApiController;
use App\Http\Resources\Finance\StudentFeeResource;
use App\Infrastructure\Persistence\Eloquent\Academic\AcademicYear;
use App\Infrastructure\Persistence\Eloquent\Academic\Classroom;
use App\Infrastructure\Persistence\Eloquent\Finance\FeeStructure;
use App\Infrastructure\Persistence\Eloquent\Finance\StudentDiscount;
use App\Infrastructure\Persistence\Eloquent\Finance\StudentFee;
use App\Infrastructure\Persistence\Eloquent\Student\Student;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class StudentFeeController extends ApiController
{
    /**
     * Display a listing of student fees.
     */
    public function index(Request $request): JsonResponse
    {
        $query = StudentFee::query()
            ->with(['student.user', 'student.currentClass', 'feeStructure.feeType', 'feeStructure.gradeLevel', 'academicYear'])
            ->when($request->search, function ($q, $search) {
                $q->whereHas('student', function ($q) use ($search) {
                    $q->where('nis', 'ilike', "%{$search}%")
                        ->orWhereHas('user', function ($q) use ($search) {
                            $q->where('first_name', 'ilike', "%{$search}%")
                                ->orWhere('last_name', 'ilike', "%{$search}%");
                        });
                });
            })
            ->when($request->student_id, fn($q, $id) => $q->where('student_id', $id))
            ->when($request->academic_year_id, fn($q, $id) => $q->where('academic_year_id', $id))
            ->when($request->fee_structure_id, fn($q, $id) => $q->where('fee_structure_id', $id))
            ->when($request->status, fn($q, $status) => $q->where('status', $status))
            ->when($request->month, fn($q, $month) => $q->where('month', $month))
            ->when($request->year, fn($q, $year) => $q->where('year', $year))
            ->when($request->classroom_id, function ($q, $classroomId) {
                $q->whereHas('student', function ($q) use ($classroomId) {
                    $q->whereHas('classrooms', function ($q) use ($classroomId) {
                        $q->where('classrooms.id', $classroomId);
                    });
                });
            })
            ->when($request->grade_level_id, function ($q, $gradeLevelId) {
                $q->whereHas('feeStructure', function ($q) use ($gradeLevelId) {
                    $q->where('grade_level_id', $gradeLevelId);
                });
            })
            ->when($request->has('is_overdue') && $request->boolean('is_overdue'), function ($q) {
                $q->where('due_date', '<', now()->toDateString())
                    ->whereIn('status', [StudentFee::STATUS_UNPAID, StudentFee::STATUS_PARTIAL]);
            });

        $sortField = $request->get('sort', 'due_date');
        $sortDirection = $request->get('direction', 'asc');
        $query->orderBy($sortField, $sortDirection);

        $perPage = $request->get('per_page', 15);
        $fees = $query->paginate($perPage);

        return $this->success(StudentFeeResource::collection($fees)->response()->getData(true));
    }

    /**
     * Store a newly created student fee.
     */
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'student_id' => ['required', 'uuid', 'exists:students,id'],
            'fee_structure_id' => ['required', 'uuid', 'exists:fee_structures,id'],
            'academic_year_id' => ['required', 'uuid', 'exists:academic_years,id'],
            'month' => ['nullable', 'integer', 'min:1', 'max:12'],
            'year' => ['nullable', 'integer', 'min:2000', 'max:2100'],
            'amount' => ['required', 'numeric', 'min:0'],
            'discount' => ['nullable', 'numeric', 'min:0'],
            'due_date' => ['required', 'date'],
            'notes' => ['nullable', 'string', 'max:500'],
        ]);

        $data['tenant_id'] = auth()->user()->tenant_id;
        $data['discount'] = $data['discount'] ?? 0;
        $data['fine'] = 0;
        $data['total_amount'] = $data['amount'] - $data['discount'];
        $data['paid_amount'] = 0;
        $data['remaining_amount'] = $data['total_amount'];
        $data['status'] = StudentFee::STATUS_UNPAID;

        // Check for duplicate
        $exists = StudentFee::where('student_id', $data['student_id'])
            ->where('fee_structure_id', $data['fee_structure_id'])
            ->where('month', $data['month'] ?? null)
            ->where('year', $data['year'] ?? null)
            ->exists();

        if ($exists) {
            return $this->error('Tagihan untuk siswa ini pada periode yang sama sudah ada', 422);
        }

        $fee = StudentFee::create($data);
        $fee->load(['student.user', 'feeStructure.feeType', 'academicYear']);

        return $this->success(
            new StudentFeeResource($fee),
            'Tagihan berhasil dibuat',
            201
        );
    }

    /**
     * Display the specified student fee.
     */
    public function show(StudentFee $studentFee): JsonResponse
    {
        $studentFee->load([
            'student.user',
            'student.currentClass',
            'feeStructure.feeType',
            'feeStructure.gradeLevel',
            'academicYear',
            'paymentItems.payment',
        ]);

        return $this->success(new StudentFeeResource($studentFee));
    }

    /**
     * Update the specified student fee.
     */
    public function update(Request $request, StudentFee $studentFee): JsonResponse
    {
        if ($studentFee->status === StudentFee::STATUS_PAID) {
            return $this->error('Tagihan yang sudah lunas tidak dapat diubah', 422);
        }

        $data = $request->validate([
            'amount' => ['sometimes', 'numeric', 'min:0'],
            'discount' => ['nullable', 'numeric', 'min:0'],
            'fine' => ['nullable', 'numeric', 'min:0'],
            'due_date' => ['sometimes', 'date'],
            'notes' => ['nullable', 'string', 'max:500'],
            'status' => ['sometimes', 'in:unpaid,partial,paid,overdue,waived'],
        ]);

        // Recalculate totals if amounts changed
        if (isset($data['amount']) || isset($data['discount']) || isset($data['fine'])) {
            $amount = $data['amount'] ?? $studentFee->amount;
            $discount = $data['discount'] ?? $studentFee->discount;
            $fine = $data['fine'] ?? $studentFee->fine;

            $data['total_amount'] = $amount - $discount + $fine;
            $data['remaining_amount'] = $data['total_amount'] - $studentFee->paid_amount;
        }

        $studentFee->update($data);
        $studentFee->load(['student.user', 'feeStructure.feeType', 'academicYear']);

        return $this->success(new StudentFeeResource($studentFee), 'Tagihan berhasil diperbarui');
    }

    /**
     * Remove the specified student fee.
     */
    public function destroy(StudentFee $studentFee): JsonResponse
    {
        if ($studentFee->paid_amount > 0) {
            return $this->error('Tagihan yang sudah ada pembayaran tidak dapat dihapus', 422);
        }

        $studentFee->delete();

        return $this->success(null, 'Tagihan berhasil dihapus');
    }

    /**
     * Generate fees in bulk for students based on fee structures.
     */
    public function generate(Request $request): JsonResponse
    {
        $data = $request->validate([
            'academic_year_id' => ['required', 'uuid', 'exists:academic_years,id'],
            'grade_level_id' => ['nullable', 'uuid', 'exists:grade_levels,id'],
            'classroom_id' => ['nullable', 'uuid', 'exists:classrooms,id'],
            'fee_type_id' => ['nullable', 'uuid', 'exists:fee_types,id'],
            'month' => ['required', 'integer', 'min:1', 'max:12'],
            'year' => ['required', 'integer', 'min:2000', 'max:2100'],
            'due_date' => ['required', 'date'],
            'apply_discounts' => ['boolean'],
        ]);

        $tenantId = auth()->user()->tenant_id;
        $applyDiscounts = $data['apply_discounts'] ?? true;

        // Get fee structures
        $structuresQuery = FeeStructure::query()
            ->where('academic_year_id', $data['academic_year_id'])
            ->where('is_active', true)
            ->when($data['grade_level_id'] ?? null, fn($q, $id) => $q->where('grade_level_id', $id))
            ->when($data['fee_type_id'] ?? null, fn($q, $id) => $q->where('fee_type_id', $id));

        $structures = $structuresQuery->get();

        if ($structures->isEmpty()) {
            return $this->error('Tidak ada struktur biaya yang ditemukan untuk kriteria yang dipilih', 422);
        }

        // Get students with active enrollment
        $studentsQuery = Student::query()
            ->where('status', 'active')
            ->whereHas('enrollments', fn($q) => $q->where('status', 'active'));

        if (!empty($data['classroom_id'])) {
            $studentsQuery->whereHas('enrollments', function ($q) use ($data) {
                $q->where('status', 'active')
                    ->where('classroom_id', $data['classroom_id']);
            });
        } elseif (!empty($data['grade_level_id'])) {
            $studentsQuery->whereHas('enrollments', function ($q) use ($data) {
                $q->where('status', 'active')
                    ->whereHas('classroom', fn($cq) => $cq->where('grade_level_id', $data['grade_level_id']));
            });
        }

        $students = $studentsQuery->with([
            'enrollments' => fn($q) => $q->where('status', 'active'),
            'enrollments.classroom.gradeLevel',
            'enrollments.classroom.major',
        ])->get();

        if ($students->isEmpty()) {
            return $this->error('Tidak ada siswa aktif yang ditemukan', 422);
        }

        $created = 0;
        $skipped = 0;
        $errors = [];

        DB::beginTransaction();
        try {
            foreach ($students as $student) {
                // Get the student's current classroom from active enrollment
                $activeEnrollment = $student->enrollments->first();
                $classroom = $activeEnrollment?->classroom;
                if (!$classroom) {
                    $skipped++;
                    continue;
                }

                // Find matching fee structures for this student
                $matchingStructures = $structures->filter(function ($structure) use ($classroom) {
                    // Match grade level
                    if ($structure->grade_level_id !== $classroom->grade_level_id) {
                        return false;
                    }
                    // Match major (if structure has major_id)
                    if ($structure->major_id && $structure->major_id !== $classroom->major_id) {
                        return false;
                    }
                    return true;
                });

                foreach ($matchingStructures as $structure) {
                    // Check if fee already exists
                    $exists = StudentFee::where('student_id', $student->id)
                        ->where('fee_structure_id', $structure->id)
                        ->where('month', $data['month'])
                        ->where('year', $data['year'])
                        ->exists();

                    if ($exists) {
                        $skipped++;
                        continue;
                    }

                    $amount = $structure->amount;
                    $discount = $structure->discount_amount ?? 0;

                    // Apply student discounts
                    if ($applyDiscounts) {
                        $studentDiscounts = StudentDiscount::where('student_id', $student->id)
                            ->where('academic_year_id', $data['academic_year_id'])
                            ->where('is_active', true)
                            ->whereNotNull('approved_at')
                            ->with('discount')
                            ->get();

                        foreach ($studentDiscounts as $sd) {
                            $discountDef = $sd->discount;
                            // Only apply if discount applies to this fee type or all fee types
                            if ($discountDef->fee_type_id === null || $discountDef->fee_type_id === $structure->fee_type_id) {
                                if ($discountDef->type === 'percentage') {
                                    $discount += $amount * ($discountDef->value / 100);
                                } else {
                                    $discount += $discountDef->value;
                                }
                            }
                        }
                    }

                    $totalAmount = max(0, $amount - $discount);

                    StudentFee::create([
                        'tenant_id' => $tenantId,
                        'student_id' => $student->id,
                        'fee_structure_id' => $structure->id,
                        'academic_year_id' => $data['academic_year_id'],
                        'month' => $data['month'],
                        'year' => $data['year'],
                        'amount' => $amount,
                        'discount' => $discount,
                        'fine' => 0,
                        'total_amount' => $totalAmount,
                        'paid_amount' => 0,
                        'remaining_amount' => $totalAmount,
                        'due_date' => $data['due_date'],
                        'status' => StudentFee::STATUS_UNPAID,
                    ]);

                    $created++;
                }
            }

            DB::commit();

            $message = "{$created} tagihan berhasil dibuat";
            if ($skipped > 0) {
                $message .= ", {$skipped} dilewati (sudah ada atau tidak sesuai kriteria)";
            }

            return $this->success([
                'created' => $created,
                'skipped' => $skipped,
            ], $message, 201);
        } catch (\Exception $e) {
            DB::rollBack();
            return $this->error('Gagal generate tagihan: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Get fee history for a specific student.
     */
    public function studentHistory(Request $request, string $studentId): JsonResponse
    {
        $student = Student::findOrFail($studentId);

        $query = StudentFee::query()
            ->with(['feeStructure.feeType', 'academicYear'])
            ->where('student_id', $studentId)
            ->when($request->academic_year_id, fn($q, $id) => $q->where('academic_year_id', $id))
            ->when($request->status, fn($q, $status) => $q->where('status', $status));

        $sortField = $request->get('sort', 'year');
        $sortDirection = $request->get('direction', 'desc');

        // Secondary sort by month for chronological order
        $query->orderBy($sortField, $sortDirection)
            ->orderBy('month', $sortDirection);

        $perPage = $request->get('per_page', 15);
        $fees = $query->paginate($perPage);

        return $this->success(StudentFeeResource::collection($fees)->response()->getData(true));
    }

    /**
     * Get summary statistics for student fees.
     */
    public function summary(Request $request): JsonResponse
    {
        $query = StudentFee::query()
            ->when($request->academic_year_id, fn($q, $id) => $q->where('academic_year_id', $id))
            ->when($request->month, fn($q, $month) => $q->where('month', $month))
            ->when($request->year, fn($q, $year) => $q->where('year', $year))
            ->when($request->classroom_id, function ($q, $classroomId) {
                $q->whereHas('student', function ($q) use ($classroomId) {
                    $q->whereHas('classrooms', function ($q) use ($classroomId) {
                        $q->where('classrooms.id', $classroomId);
                    });
                });
            });

        $summary = [
            'total_billed' => (clone $query)->sum('total_amount'),
            'total_discount' => (clone $query)->sum('discount'),
            'total_fine' => (clone $query)->sum('fine'),
            'total_paid' => (clone $query)->sum('paid_amount'),
            'total_remaining' => (clone $query)->sum('remaining_amount'),
            'count_total' => (clone $query)->count(),
            'count_unpaid' => (clone $query)->where('status', StudentFee::STATUS_UNPAID)->count(),
            'count_partial' => (clone $query)->where('status', StudentFee::STATUS_PARTIAL)->count(),
            'count_paid' => (clone $query)->where('status', StudentFee::STATUS_PAID)->count(),
            'count_overdue' => (clone $query)->where('status', StudentFee::STATUS_OVERDUE)->count(),
            'count_waived' => (clone $query)->where('status', StudentFee::STATUS_WAIVED)->count(),
        ];

        // Format amounts
        $summary['total_billed_formatted'] = 'Rp ' . number_format($summary['total_billed'], 0, ',', '.');
        $summary['total_paid_formatted'] = 'Rp ' . number_format($summary['total_paid'], 0, ',', '.');
        $summary['total_remaining_formatted'] = 'Rp ' . number_format($summary['total_remaining'], 0, ',', '.');

        return $this->success($summary);
    }

    /**
     * Waive a student fee (bebaskan tagihan).
     */
    public function waive(Request $request, StudentFee $studentFee): JsonResponse
    {
        if ($studentFee->status === StudentFee::STATUS_PAID) {
            return $this->error('Tagihan yang sudah lunas tidak dapat dibebaskan', 422);
        }

        $data = $request->validate([
            'reason' => ['required', 'string', 'max:500'],
        ]);

        $studentFee->update([
            'status' => StudentFee::STATUS_WAIVED,
            'remaining_amount' => 0,
            'notes' => $data['reason'],
        ]);

        return $this->success(null, 'Tagihan berhasil dibebaskan');
    }

    /**
     * Get available statuses.
     */
    public function statuses(): JsonResponse
    {
        return $this->success(StudentFee::getStatuses());
    }
}
