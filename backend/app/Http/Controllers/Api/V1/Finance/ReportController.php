<?php

namespace App\Http\Controllers\Api\V1\Finance;

use App\Http\Controllers\Controller;
use App\Infrastructure\Persistence\Eloquent\Academic\AcademicYear;
use App\Infrastructure\Persistence\Eloquent\Academic\Classroom;
use App\Infrastructure\Persistence\Eloquent\Finance\Payment;
use App\Infrastructure\Persistence\Eloquent\Finance\StudentFee;
use App\Infrastructure\Persistence\Eloquent\Student\Student;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ReportController extends Controller
{
    /**
     * Get finance dashboard summary.
     */
    public function dashboard(Request $request): JsonResponse
    {
        $academicYearId = $request->input('academic_year_id');

        // Get current academic year if not specified
        if (!$academicYearId) {
            $currentYear = AcademicYear::where('is_active', true)->first();
            $academicYearId = $currentYear?->id;
        }

        // Total students with fees
        $totalStudents = StudentFee::when($academicYearId, fn($q) => $q->where('academic_year_id', $academicYearId))
            ->distinct('student_id')
            ->count('student_id');

        // Total fees amount
        $totalFees = StudentFee::when($academicYearId, fn($q) => $q->where('academic_year_id', $academicYearId))
            ->sum('total_amount');

        // Total paid amount
        $totalPaid = StudentFee::when($academicYearId, fn($q) => $q->where('academic_year_id', $academicYearId))
            ->sum('paid_amount');

        // Outstanding amount
        $totalOutstanding = StudentFee::when($academicYearId, fn($q) => $q->where('academic_year_id', $academicYearId))
            ->unpaid()
            ->sum('remaining_amount');

        // Payment collection this month
        $monthlyPayments = Payment::completed()
            ->whereMonth('paid_at', now()->month)
            ->whereYear('paid_at', now()->year)
            ->sum('grand_total');

        // Students with overdue fees
        $overdueCount = StudentFee::when($academicYearId, fn($q) => $q->where('academic_year_id', $academicYearId))
            ->where('status', StudentFee::STATUS_OVERDUE)
            ->distinct('student_id')
            ->count('student_id');

        // Fee status breakdown
        $statusBreakdown = StudentFee::when($academicYearId, fn($q) => $q->where('academic_year_id', $academicYearId))
            ->select('status', DB::raw('COUNT(*) as count'), DB::raw('SUM(total_amount) as amount'))
            ->groupBy('status')
            ->get()
            ->mapWithKeys(fn($item) => [$item->status => [
                'count' => $item->count,
                'amount' => $item->amount,
                'amount_formatted' => 'Rp ' . number_format($item->amount, 0, ',', '.'),
            ]]);

        // Recent payments (last 5)
        $recentPayments = Payment::completed()
            ->with(['student.user'])
            ->latest('paid_at')
            ->take(5)
            ->get()
            ->map(fn($payment) => [
                'id' => $payment->id,
                'invoice_number' => $payment->invoice_number,
                'student_name' => $payment->student?->user?->name ?? '-',
                'amount' => $payment->grand_total,
                'amount_formatted' => 'Rp ' . number_format($payment->grand_total, 0, ',', '.'),
                'paid_at' => $payment->paid_at?->format('d M Y H:i'),
            ]);

        // Monthly trend (last 6 months)
        $monthlyTrend = Payment::completed()
            ->where('paid_at', '>=', now()->subMonths(6)->startOfMonth())
            ->select(
                DB::raw('EXTRACT(YEAR FROM paid_at) as year'),
                DB::raw('EXTRACT(MONTH FROM paid_at) as month'),
                DB::raw('SUM(grand_total) as total')
            )
            ->groupBy('year', 'month')
            ->orderBy('year')
            ->orderBy('month')
            ->get()
            ->map(fn($item) => [
                'year' => (int) $item->year,
                'month' => (int) $item->month,
                'month_name' => $this->getMonthName((int) $item->month),
                'total' => $item->total,
                'total_formatted' => 'Rp ' . number_format($item->total, 0, ',', '.'),
            ]);

        return response()->json([
            'data' => [
                'summary' => [
                    'total_students' => $totalStudents,
                    'total_fees' => $totalFees,
                    'total_fees_formatted' => 'Rp ' . number_format($totalFees, 0, ',', '.'),
                    'total_paid' => $totalPaid,
                    'total_paid_formatted' => 'Rp ' . number_format($totalPaid, 0, ',', '.'),
                    'total_outstanding' => $totalOutstanding,
                    'total_outstanding_formatted' => 'Rp ' . number_format($totalOutstanding, 0, ',', '.'),
                    'monthly_payments' => $monthlyPayments,
                    'monthly_payments_formatted' => 'Rp ' . number_format($monthlyPayments, 0, ',', '.'),
                    'overdue_students' => $overdueCount,
                    'collection_rate' => $totalFees > 0 ? round(($totalPaid / $totalFees) * 100, 1) : 0,
                ],
                'status_breakdown' => $statusBreakdown,
                'recent_payments' => $recentPayments,
                'monthly_trend' => $monthlyTrend,
            ],
        ]);
    }

    /**
     * Get outstanding fees report (tunggakan).
     */
    public function outstanding(Request $request): JsonResponse
    {
        $request->validate([
            'academic_year_id' => 'nullable|uuid|exists:academic_years,id',
            'classroom_id' => 'nullable|uuid|exists:classrooms,id',
            'status' => 'nullable|in:unpaid,partial,overdue',
            'due_before' => 'nullable|date',
            'per_page' => 'nullable|integer|min:1|max:100',
        ]);

        $query = StudentFee::query()
            ->with(['student.user', 'student.currentClass', 'feeStructure.feeType', 'academicYear'])
            ->unpaid();

        if ($request->filled('academic_year_id')) {
            $query->where('academic_year_id', $request->academic_year_id);
        }

        if ($request->filled('classroom_id')) {
            $query->whereHas('student.enrollments', function ($q) use ($request) {
                $q->where('classroom_id', $request->classroom_id)
                    ->where('status', 'active');
            });
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('due_before')) {
            $query->dueBefore($request->due_before);
        }

        // Summary
        $summary = [
            'total_outstanding' => (clone $query)->sum('remaining_amount'),
            'total_students' => (clone $query)->distinct('student_id')->count('student_id'),
            'total_fees' => (clone $query)->count(),
        ];
        $summary['total_outstanding_formatted'] = 'Rp ' . number_format($summary['total_outstanding'], 0, ',', '.');

        // Paginated data
        $data = $query->orderBy('due_date')
            ->orderBy('remaining_amount', 'desc')
            ->paginate($request->input('per_page', 15));

        $data->getCollection()->transform(fn($fee) => [
            'id' => $fee->id,
            'student' => [
                'id' => $fee->student_id,
                'name' => $fee->student?->user?->name ?? '-',
                'nis' => $fee->student?->nis ?? '-',
                'classroom' => $fee->student?->currentClass?->name ?? '-',
            ],
            'fee_type' => $fee->feeStructure?->feeType?->name ?? '-',
            'period' => $fee->month ? $this->getMonthName($fee->month) . ' ' . $fee->year : $fee->academicYear?->name ?? '-',
            'total_amount' => $fee->total_amount,
            'total_amount_formatted' => 'Rp ' . number_format($fee->total_amount, 0, ',', '.'),
            'paid_amount' => $fee->paid_amount,
            'paid_amount_formatted' => 'Rp ' . number_format($fee->paid_amount, 0, ',', '.'),
            'remaining_amount' => $fee->remaining_amount,
            'remaining_amount_formatted' => 'Rp ' . number_format($fee->remaining_amount, 0, ',', '.'),
            'due_date' => $fee->due_date?->format('d M Y'),
            'status' => $fee->status,
            'status_label' => $fee->getStatusLabel(),
            'days_overdue' => $fee->due_date && $fee->due_date < now() ? now()->diffInDays($fee->due_date) : 0,
        ]);

        return response()->json([
            'data' => $data->items(),
            'meta' => [
                'current_page' => $data->currentPage(),
                'last_page' => $data->lastPage(),
                'per_page' => $data->perPage(),
                'total' => $data->total(),
            ],
            'summary' => $summary,
        ]);
    }

    /**
     * Get per-class payment report.
     */
    public function byClassroom(Request $request): JsonResponse
    {
        $request->validate([
            'academic_year_id' => 'nullable|uuid|exists:academic_years,id',
            'month' => 'nullable|integer|min:1|max:12',
            'year' => 'nullable|integer|min:2000|max:2100',
        ]);

        $academicYearId = $request->input('academic_year_id');
        if (!$academicYearId) {
            $currentYear = AcademicYear::where('is_active', true)->first();
            $academicYearId = $currentYear?->id;
        }

        $classrooms = Classroom::query()
            ->with(['gradeLevel', 'major'])
            ->get()
            ->map(function ($classroom) use ($academicYearId, $request) {
                $query = StudentFee::query()
                    ->whereHas('student.enrollments', function ($q) use ($classroom) {
                        $q->where('classroom_id', $classroom->id)
                            ->where('status', 'active');
                    });

                if ($academicYearId) {
                    $query->where('academic_year_id', $academicYearId);
                }

                if ($request->filled('month') && $request->filled('year')) {
                    $query->forPeriod($request->month, $request->year);
                }

                $totalFees = (clone $query)->sum('total_amount');
                $totalPaid = (clone $query)->sum('paid_amount');
                $totalOutstanding = (clone $query)->sum('remaining_amount');
                $studentCount = (clone $query)->distinct('student_id')->count('student_id');
                $unpaidCount = (clone $query)->unpaid()->distinct('student_id')->count('student_id');

                return [
                    'id' => $classroom->id,
                    'name' => $classroom->name,
                    'grade_level' => $classroom->gradeLevel?->name ?? '-',
                    'major' => $classroom->major?->name ?? '-',
                    'student_count' => $studentCount,
                    'unpaid_count' => $unpaidCount,
                    'total_fees' => $totalFees,
                    'total_fees_formatted' => 'Rp ' . number_format($totalFees, 0, ',', '.'),
                    'total_paid' => $totalPaid,
                    'total_paid_formatted' => 'Rp ' . number_format($totalPaid, 0, ',', '.'),
                    'total_outstanding' => $totalOutstanding,
                    'total_outstanding_formatted' => 'Rp ' . number_format($totalOutstanding, 0, ',', '.'),
                    'collection_rate' => $totalFees > 0 ? round(($totalPaid / $totalFees) * 100, 1) : 0,
                ];
            })
            ->filter(fn($item) => $item['student_count'] > 0)
            ->sortByDesc('total_outstanding')
            ->values();

        // Summary
        $summary = [
            'total_classrooms' => $classrooms->count(),
            'total_students' => $classrooms->sum('student_count'),
            'total_fees' => $classrooms->sum('total_fees'),
            'total_paid' => $classrooms->sum('total_paid'),
            'total_outstanding' => $classrooms->sum('total_outstanding'),
        ];
        $summary['total_fees_formatted'] = 'Rp ' . number_format($summary['total_fees'], 0, ',', '.');
        $summary['total_paid_formatted'] = 'Rp ' . number_format($summary['total_paid'], 0, ',', '.');
        $summary['total_outstanding_formatted'] = 'Rp ' . number_format($summary['total_outstanding'], 0, ',', '.');
        $summary['collection_rate'] = $summary['total_fees'] > 0 ? round(($summary['total_paid'] / $summary['total_fees']) * 100, 1) : 0;

        return response()->json([
            'data' => $classrooms,
            'summary' => $summary,
        ]);
    }

    /**
     * Get monthly billing report for students.
     */
    public function monthly(Request $request): JsonResponse
    {
        $request->validate([
            'academic_year_id' => 'nullable|uuid|exists:academic_years,id',
            'month' => 'required|integer|min:1|max:12',
            'year' => 'required|integer|min:2000|max:2100',
            'classroom_id' => 'nullable|uuid|exists:classrooms,id',
            'status' => 'nullable|in:unpaid,partial,paid,overdue',
            'per_page' => 'nullable|integer|min:1|max:100',
        ]);

        $query = StudentFee::query()
            ->with(['student.user', 'student.currentClass', 'feeStructure.feeType'])
            ->forPeriod($request->month, $request->year);

        if ($request->filled('academic_year_id')) {
            $query->where('academic_year_id', $request->academic_year_id);
        }

        if ($request->filled('classroom_id')) {
            $query->whereHas('student.enrollments', function ($q) use ($request) {
                $q->where('classroom_id', $request->classroom_id)
                    ->where('status', 'active');
            });
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        // Summary
        $summary = [
            'period' => $this->getMonthName($request->month) . ' ' . $request->year,
            'total_students' => (clone $query)->distinct('student_id')->count('student_id'),
            'total_fees' => (clone $query)->sum('total_amount'),
            'total_paid' => (clone $query)->sum('paid_amount'),
            'total_outstanding' => (clone $query)->sum('remaining_amount'),
            'paid_count' => (clone $query)->paid()->count(),
            'unpaid_count' => (clone $query)->unpaid()->count(),
        ];
        $summary['total_fees_formatted'] = 'Rp ' . number_format($summary['total_fees'], 0, ',', '.');
        $summary['total_paid_formatted'] = 'Rp ' . number_format($summary['total_paid'], 0, ',', '.');
        $summary['total_outstanding_formatted'] = 'Rp ' . number_format($summary['total_outstanding'], 0, ',', '.');

        // Paginated data
        $data = $query->orderBy('status')
            ->paginate($request->input('per_page', 15));

        $data->getCollection()->transform(fn($fee) => [
            'id' => $fee->id,
            'student' => [
                'id' => $fee->student_id,
                'name' => $fee->student?->user?->name ?? '-',
                'nis' => $fee->student?->nis ?? '-',
                'classroom' => $fee->student?->currentClass?->name ?? '-',
            ],
            'fee_type' => $fee->feeStructure?->feeType?->name ?? '-',
            'amount' => $fee->amount,
            'amount_formatted' => 'Rp ' . number_format($fee->amount, 0, ',', '.'),
            'discount' => $fee->discount,
            'discount_formatted' => 'Rp ' . number_format($fee->discount, 0, ',', '.'),
            'fine' => $fee->fine,
            'fine_formatted' => 'Rp ' . number_format($fee->fine, 0, ',', '.'),
            'total_amount' => $fee->total_amount,
            'total_amount_formatted' => 'Rp ' . number_format($fee->total_amount, 0, ',', '.'),
            'paid_amount' => $fee->paid_amount,
            'paid_amount_formatted' => 'Rp ' . number_format($fee->paid_amount, 0, ',', '.'),
            'remaining_amount' => $fee->remaining_amount,
            'remaining_amount_formatted' => 'Rp ' . number_format($fee->remaining_amount, 0, ',', '.'),
            'due_date' => $fee->due_date?->format('d M Y'),
            'status' => $fee->status,
            'status_label' => $fee->getStatusLabel(),
        ]);

        return response()->json([
            'data' => $data->items(),
            'meta' => [
                'current_page' => $data->currentPage(),
                'last_page' => $data->lastPage(),
                'per_page' => $data->perPage(),
                'total' => $data->total(),
            ],
            'summary' => $summary,
        ]);
    }

    /**
     * Export outstanding fees to CSV.
     */
    public function exportOutstanding(Request $request): \Symfony\Component\HttpFoundation\StreamedResponse
    {
        $request->validate([
            'academic_year_id' => 'nullable|uuid|exists:academic_years,id',
            'classroom_id' => 'nullable|uuid|exists:classrooms,id',
        ]);

        $query = StudentFee::query()
            ->with(['student.user', 'student.currentClass', 'feeStructure.feeType', 'academicYear'])
            ->unpaid();

        if ($request->filled('academic_year_id')) {
            $query->where('academic_year_id', $request->academic_year_id);
        }

        if ($request->filled('classroom_id')) {
            $query->whereHas('student.enrollments', function ($q) use ($request) {
                $q->where('classroom_id', $request->classroom_id)
                    ->where('status', 'active');
            });
        }

        $data = $query->orderBy('due_date')->get();

        $filename = 'tunggakan_' . now()->format('Ymd_His') . '.csv';

        return response()->streamDownload(function () use ($data) {
            $handle = fopen('php://output', 'w');

            // BOM for Excel UTF-8
            fprintf($handle, chr(0xEF) . chr(0xBB) . chr(0xBF));

            // Header
            fputcsv($handle, [
                'NIS',
                'Nama Siswa',
                'Kelas',
                'Jenis Tagihan',
                'Periode',
                'Total Tagihan',
                'Sudah Dibayar',
                'Sisa Tunggakan',
                'Jatuh Tempo',
                'Status',
            ], ';');

            foreach ($data as $fee) {
                fputcsv($handle, [
                    $fee->student?->nis ?? '-',
                    $fee->student?->user?->name ?? '-',
                    $fee->student?->currentClass?->name ?? '-',
                    $fee->feeStructure?->feeType?->name ?? '-',
                    $fee->month ? $this->getMonthName($fee->month) . ' ' . $fee->year : ($fee->academicYear?->name ?? '-'),
                    $fee->total_amount,
                    $fee->paid_amount,
                    $fee->remaining_amount,
                    $fee->due_date?->format('d/m/Y') ?? '-',
                    $fee->getStatusLabel(),
                ], ';');
            }

            fclose($handle);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    /**
     * Export classroom report to CSV.
     */
    public function exportByClassroom(Request $request): \Symfony\Component\HttpFoundation\StreamedResponse
    {
        $request->validate([
            'academic_year_id' => 'nullable|uuid|exists:academic_years,id',
            'month' => 'nullable|integer|min:1|max:12',
            'year' => 'nullable|integer|min:2000|max:2100',
        ]);

        $academicYearId = $request->input('academic_year_id');
        if (!$academicYearId) {
            $currentYear = AcademicYear::where('is_active', true)->first();
            $academicYearId = $currentYear?->id;
        }

        $classrooms = Classroom::query()
            ->with(['gradeLevel', 'major'])
            ->get()
            ->map(function ($classroom) use ($academicYearId, $request) {
                $query = StudentFee::query()
                    ->whereHas('student.enrollments', function ($q) use ($classroom) {
                        $q->where('classroom_id', $classroom->id)
                            ->where('status', 'active');
                    });

                if ($academicYearId) {
                    $query->where('academic_year_id', $academicYearId);
                }

                if ($request->filled('month') && $request->filled('year')) {
                    $query->forPeriod($request->month, $request->year);
                }

                return [
                    'name' => $classroom->name,
                    'grade_level' => $classroom->gradeLevel?->name ?? '-',
                    'major' => $classroom->major?->name ?? '-',
                    'student_count' => (clone $query)->distinct('student_id')->count('student_id'),
                    'total_fees' => (clone $query)->sum('total_amount'),
                    'total_paid' => (clone $query)->sum('paid_amount'),
                    'total_outstanding' => (clone $query)->sum('remaining_amount'),
                ];
            })
            ->filter(fn($item) => $item['student_count'] > 0)
            ->sortByDesc('total_outstanding')
            ->values();

        $filename = 'laporan_per_kelas_' . now()->format('Ymd_His') . '.csv';

        return response()->streamDownload(function () use ($classrooms) {
            $handle = fopen('php://output', 'w');

            fprintf($handle, chr(0xEF) . chr(0xBB) . chr(0xBF));

            fputcsv($handle, [
                'Kelas',
                'Tingkat',
                'Jurusan',
                'Jumlah Siswa',
                'Total Tagihan',
                'Total Terbayar',
                'Total Tunggakan',
                '% Terbayar',
            ], ';');

            foreach ($classrooms as $classroom) {
                $rate = $classroom['total_fees'] > 0 ? round(($classroom['total_paid'] / $classroom['total_fees']) * 100, 1) : 0;
                fputcsv($handle, [
                    $classroom['name'],
                    $classroom['grade_level'],
                    $classroom['major'],
                    $classroom['student_count'],
                    $classroom['total_fees'],
                    $classroom['total_paid'],
                    $classroom['total_outstanding'],
                    $rate . '%',
                ], ';');
            }

            fclose($handle);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    /**
     * Export monthly report to CSV.
     */
    public function exportMonthly(Request $request): \Symfony\Component\HttpFoundation\StreamedResponse
    {
        $request->validate([
            'month' => 'required|integer|min:1|max:12',
            'year' => 'required|integer|min:2000|max:2100',
            'academic_year_id' => 'nullable|uuid|exists:academic_years,id',
            'classroom_id' => 'nullable|uuid|exists:classrooms,id',
        ]);

        $query = StudentFee::query()
            ->with(['student.user', 'student.currentClass', 'feeStructure.feeType'])
            ->forPeriod($request->month, $request->year);

        if ($request->filled('academic_year_id')) {
            $query->where('academic_year_id', $request->academic_year_id);
        }

        if ($request->filled('classroom_id')) {
            $query->whereHas('student.enrollments', function ($q) use ($request) {
                $q->where('classroom_id', $request->classroom_id)
                    ->where('status', 'active');
            });
        }

        $data = $query->orderBy('status')->get();

        $period = $this->getMonthName($request->month) . '_' . $request->year;
        $filename = 'tagihan_bulanan_' . $period . '_' . now()->format('Ymd_His') . '.csv';

        return response()->streamDownload(function () use ($data) {
            $handle = fopen('php://output', 'w');

            fprintf($handle, chr(0xEF) . chr(0xBB) . chr(0xBF));

            fputcsv($handle, [
                'NIS',
                'Nama Siswa',
                'Kelas',
                'Jenis Tagihan',
                'Nominal',
                'Diskon',
                'Denda',
                'Total Tagihan',
                'Terbayar',
                'Sisa',
                'Jatuh Tempo',
                'Status',
            ], ';');

            foreach ($data as $fee) {
                fputcsv($handle, [
                    $fee->student?->nis ?? '-',
                    $fee->student?->user?->name ?? '-',
                    $fee->student?->currentClass?->name ?? '-',
                    $fee->feeStructure?->feeType?->name ?? '-',
                    $fee->amount,
                    $fee->discount,
                    $fee->fine,
                    $fee->total_amount,
                    $fee->paid_amount,
                    $fee->remaining_amount,
                    $fee->due_date?->format('d/m/Y') ?? '-',
                    $fee->getStatusLabel(),
                ], ';');
            }

            fclose($handle);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    /**
     * Get student fee history.
     */
    public function studentHistory(Request $request, string $studentId): JsonResponse
    {
        $student = Student::with('user')->findOrFail($studentId);

        $fees = StudentFee::query()
            ->with(['feeStructure.feeType', 'academicYear', 'paymentItems.payment'])
            ->where('student_id', $studentId)
            ->orderBy('year', 'desc')
            ->orderBy('month', 'desc')
            ->orderBy('due_date', 'desc')
            ->get()
            ->map(fn($fee) => [
                'id' => $fee->id,
                'fee_type' => $fee->feeStructure?->feeType?->name ?? '-',
                'period' => $fee->month ? $this->getMonthName($fee->month) . ' ' . $fee->year : ($fee->academicYear?->name ?? '-'),
                'total_amount' => $fee->total_amount,
                'total_amount_formatted' => 'Rp ' . number_format($fee->total_amount, 0, ',', '.'),
                'paid_amount' => $fee->paid_amount,
                'paid_amount_formatted' => 'Rp ' . number_format($fee->paid_amount, 0, ',', '.'),
                'remaining_amount' => $fee->remaining_amount,
                'remaining_amount_formatted' => 'Rp ' . number_format($fee->remaining_amount, 0, ',', '.'),
                'due_date' => $fee->due_date?->format('d M Y'),
                'status' => $fee->status,
                'status_label' => $fee->getStatusLabel(),
                'payments' => $fee->paymentItems->map(fn($item) => [
                    'id' => $item->payment?->id,
                    'invoice_number' => $item->payment?->invoice_number,
                    'amount' => $item->amount,
                    'amount_formatted' => 'Rp ' . number_format($item->amount, 0, ',', '.'),
                    'paid_at' => $item->payment?->paid_at?->format('d M Y'),
                    'status' => $item->payment?->status,
                ]),
            ]);

        // Summary
        $totalFees = $fees->sum('total_amount');
        $totalPaid = $fees->sum('paid_amount');
        $totalOutstanding = $fees->sum('remaining_amount');

        return response()->json([
            'data' => [
                'student' => [
                    'id' => $student->id,
                    'name' => $student->user?->name ?? '-',
                    'nis' => $student->nis,
                ],
                'summary' => [
                    'total_fees' => $totalFees,
                    'total_fees_formatted' => 'Rp ' . number_format($totalFees, 0, ',', '.'),
                    'total_paid' => $totalPaid,
                    'total_paid_formatted' => 'Rp ' . number_format($totalPaid, 0, ',', '.'),
                    'total_outstanding' => $totalOutstanding,
                    'total_outstanding_formatted' => 'Rp ' . number_format($totalOutstanding, 0, ',', '.'),
                ],
                'fees' => $fees,
            ],
        ]);
    }

    private function getMonthName(int $month): string
    {
        $months = [
            1 => 'Januari', 2 => 'Februari', 3 => 'Maret',
            4 => 'April', 5 => 'Mei', 6 => 'Juni',
            7 => 'Juli', 8 => 'Agustus', 9 => 'September',
            10 => 'Oktober', 11 => 'November', 12 => 'Desember',
        ];
        return $months[$month] ?? (string) $month;
    }
}
