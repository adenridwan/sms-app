<?php

namespace App\Http\Controllers\Api\V1\Payroll;

use App\Http\Controllers\Controller;
use App\Infrastructure\Persistence\Eloquent\Payroll\PayrollPeriod;
use App\Infrastructure\Persistence\Eloquent\Payroll\PayrollSlip;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ReportController extends Controller
{
    /**
     * Get payroll dashboard summary.
     */
    public function dashboard(Request $request): JsonResponse
    {
        $year = $request->input('year', now()->year);

        // Total employees with active salary
        $totalEmployees = PayrollSlip::query()
            ->whereHas('period', fn($q) => $q->year($year))
            ->distinct('employee_id', 'employee_type')
            ->count();

        // Total gross salary for the year
        $totalGross = PayrollPeriod::query()
            ->year($year)
            ->finalized()
            ->sum('total_gross');

        // Total deductions for the year
        $totalDeductions = PayrollPeriod::query()
            ->year($year)
            ->finalized()
            ->sum('total_deductions');

        // Total net salary for the year
        $totalNet = PayrollPeriod::query()
            ->year($year)
            ->finalized()
            ->sum('total_net');

        // Current month payroll
        $currentMonth = PayrollPeriod::query()
            ->year(now()->year)
            ->month(now()->month)
            ->first();

        // Total BPJS for the year
        $totalBpjs = PayrollSlip::query()
            ->whereHas('period', fn($q) => $q->year($year)->finalized())
            ->selectRaw('SUM(bpjs_kesehatan + bpjs_jht + bpjs_jp) as total')
            ->value('total') ?? 0;

        // Total PPh 21 for the year
        $totalPph21 = PayrollSlip::query()
            ->whereHas('period', fn($q) => $q->year($year)->finalized())
            ->sum('pph21');

        // Monthly trend
        $monthlyTrend = PayrollPeriod::query()
            ->year($year)
            ->finalized()
            ->orderBy('month')
            ->get()
            ->map(fn($period) => [
                'month' => $period->month,
                'month_name' => $this->getMonthName($period->month),
                'total_gross' => $period->total_gross,
                'total_gross_formatted' => 'Rp ' . number_format($period->total_gross, 0, ',', '.'),
                'total_deductions' => $period->total_deductions,
                'total_deductions_formatted' => 'Rp ' . number_format($period->total_deductions, 0, ',', '.'),
                'total_net' => $period->total_net,
                'total_net_formatted' => 'Rp ' . number_format($period->total_net, 0, ',', '.'),
                'employee_count' => $period->employee_count,
            ]);

        return response()->json([
            'data' => [
                'summary' => [
                    'year' => $year,
                    'total_employees' => $totalEmployees,
                    'total_gross' => $totalGross,
                    'total_gross_formatted' => 'Rp ' . number_format($totalGross, 0, ',', '.'),
                    'total_deductions' => $totalDeductions,
                    'total_deductions_formatted' => 'Rp ' . number_format($totalDeductions, 0, ',', '.'),
                    'total_net' => $totalNet,
                    'total_net_formatted' => 'Rp ' . number_format($totalNet, 0, ',', '.'),
                    'total_bpjs' => $totalBpjs,
                    'total_bpjs_formatted' => 'Rp ' . number_format($totalBpjs, 0, ',', '.'),
                    'total_pph21' => $totalPph21,
                    'total_pph21_formatted' => 'Rp ' . number_format($totalPph21, 0, ',', '.'),
                ],
                'current_month' => $currentMonth ? [
                    'id' => $currentMonth->id,
                    'name' => $currentMonth->name,
                    'status' => $currentMonth->status,
                    'status_label' => $currentMonth->getStatusLabel(),
                    'total_gross' => $currentMonth->total_gross,
                    'total_gross_formatted' => 'Rp ' . number_format($currentMonth->total_gross, 0, ',', '.'),
                    'total_net' => $currentMonth->total_net,
                    'total_net_formatted' => 'Rp ' . number_format($currentMonth->total_net, 0, ',', '.'),
                    'employee_count' => $currentMonth->employee_count,
                ] : null,
                'monthly_trend' => $monthlyTrend,
            ],
        ]);
    }

    /**
     * Get monthly payroll recap report.
     */
    public function monthlyRecap(Request $request): JsonResponse
    {
        $request->validate([
            'year' => 'required|integer|min:2000|max:2100',
            'month' => 'nullable|integer|min:1|max:12',
        ]);

        $query = PayrollPeriod::query()
            ->year($request->year)
            ->orderBy('month');

        if ($request->filled('month')) {
            $query->month($request->month);
        }

        $periods = $query->get()->map(fn($period) => [
            'id' => $period->id,
            'name' => $period->name,
            'month' => $period->month,
            'month_name' => $this->getMonthName($period->month),
            'year' => $period->year,
            'status' => $period->status,
            'status_label' => $period->getStatusLabel(),
            'employee_count' => $period->employee_count,
            'total_gross' => $period->total_gross,
            'total_gross_formatted' => 'Rp ' . number_format($period->total_gross, 0, ',', '.'),
            'total_deductions' => $period->total_deductions,
            'total_deductions_formatted' => 'Rp ' . number_format($period->total_deductions, 0, ',', '.'),
            'total_net' => $period->total_net,
            'total_net_formatted' => 'Rp ' . number_format($period->total_net, 0, ',', '.'),
            'payment_date' => $period->payment_date?->format('d M Y'),
            'approved_at' => $period->approved_at?->format('d M Y'),
            'finalized_at' => $period->finalized_at?->format('d M Y'),
        ]);

        // Summary
        $summary = [
            'year' => $request->year,
            'total_periods' => $periods->count(),
            'total_gross' => $periods->sum('total_gross'),
            'total_deductions' => $periods->sum('total_deductions'),
            'total_net' => $periods->sum('total_net'),
        ];
        $summary['total_gross_formatted'] = 'Rp ' . number_format($summary['total_gross'], 0, ',', '.');
        $summary['total_deductions_formatted'] = 'Rp ' . number_format($summary['total_deductions'], 0, ',', '.');
        $summary['total_net_formatted'] = 'Rp ' . number_format($summary['total_net'], 0, ',', '.');

        return response()->json([
            'data' => $periods,
            'summary' => $summary,
        ]);
    }

    /**
     * Get PPh 21 tax report.
     */
    public function pph21(Request $request): JsonResponse
    {
        $request->validate([
            'year' => 'required|integer|min:2000|max:2100',
            'month' => 'nullable|integer|min:1|max:12',
            'period_id' => 'nullable|uuid|exists:payroll_periods,id',
            'per_page' => 'nullable|integer|min:1|max:100',
        ]);

        $query = PayrollSlip::query()
            ->with(['period', 'employeeSalary'])
            ->whereHas('period', function ($q) use ($request) {
                $q->year($request->year);
                if ($request->filled('month')) {
                    $q->month($request->month);
                }
            })
            ->where('pph21', '>', 0);

        if ($request->filled('period_id')) {
            $query->where('payroll_period_id', $request->period_id);
        }

        // Summary
        $summaryQuery = clone $query;
        $summary = [
            'year' => $request->year,
            'month' => $request->month,
            'month_name' => $request->month ? $this->getMonthName($request->month) : 'Semua Bulan',
            'total_employees' => $summaryQuery->count(),
            'total_gross' => $summaryQuery->sum('gross_salary'),
            'total_pph21' => $summaryQuery->sum('pph21'),
        ];
        $summary['total_gross_formatted'] = 'Rp ' . number_format($summary['total_gross'], 0, ',', '.');
        $summary['total_pph21_formatted'] = 'Rp ' . number_format($summary['total_pph21'], 0, ',', '.');

        // Monthly breakdown
        $monthlyBreakdown = PayrollSlip::query()
            ->select(
                DB::raw('EXTRACT(MONTH FROM payroll_periods.month) as period_month'),
                DB::raw('SUM(payroll_slips.pph21) as total_pph21'),
                DB::raw('COUNT(DISTINCT payroll_slips.id) as employee_count')
            )
            ->join('payroll_periods', 'payroll_slips.payroll_period_id', '=', 'payroll_periods.id')
            ->where('payroll_periods.year', $request->year)
            ->where('payroll_slips.pph21', '>', 0)
            ->groupBy('payroll_periods.month')
            ->orderBy('payroll_periods.month')
            ->get()
            ->map(fn($item) => [
                'month' => (int) $item->period_month,
                'month_name' => $this->getMonthName((int) $item->period_month),
                'employee_count' => $item->employee_count,
                'total_pph21' => $item->total_pph21,
                'total_pph21_formatted' => 'Rp ' . number_format($item->total_pph21, 0, ',', '.'),
            ]);

        // Paginated data
        $data = $query->orderBy('pph21', 'desc')
            ->paginate($request->input('per_page', 15));

        $data->getCollection()->transform(fn($slip) => [
            'id' => $slip->id,
            'period' => $slip->period?->getPeriodLabel() ?? '-',
            'employee_name' => $slip->employee_name,
            'employee_identifier' => $slip->employee_identifier,
            'employee_type' => $slip->employee_type,
            'employee_type_label' => $slip->getEmployeeTypeLabel(),
            'ptkp_status' => $slip->ptkp_status,
            'gross_salary' => $slip->gross_salary,
            'gross_salary_formatted' => 'Rp ' . number_format($slip->gross_salary, 0, ',', '.'),
            'pph21' => $slip->pph21,
            'pph21_formatted' => 'Rp ' . number_format($slip->pph21, 0, ',', '.'),
            'net_salary' => $slip->net_salary,
            'net_salary_formatted' => 'Rp ' . number_format($slip->net_salary, 0, ',', '.'),
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
            'monthly_breakdown' => $monthlyBreakdown,
        ]);
    }

    /**
     * Get BPJS report.
     */
    public function bpjs(Request $request): JsonResponse
    {
        $request->validate([
            'year' => 'required|integer|min:2000|max:2100',
            'month' => 'nullable|integer|min:1|max:12',
            'period_id' => 'nullable|uuid|exists:payroll_periods,id',
            'type' => 'nullable|in:kesehatan,jht,jp,all',
            'per_page' => 'nullable|integer|min:1|max:100',
        ]);

        $query = PayrollSlip::query()
            ->with(['period', 'employeeSalary'])
            ->whereHas('period', function ($q) use ($request) {
                $q->year($request->year);
                if ($request->filled('month')) {
                    $q->month($request->month);
                }
            })
            ->where(function ($q) {
                $q->where('bpjs_kesehatan', '>', 0)
                    ->orWhere('bpjs_jht', '>', 0)
                    ->orWhere('bpjs_jp', '>', 0);
            });

        if ($request->filled('period_id')) {
            $query->where('payroll_period_id', $request->period_id);
        }

        // Summary
        $summaryQuery = clone $query;
        $summary = [
            'year' => $request->year,
            'month' => $request->month,
            'month_name' => $request->month ? $this->getMonthName($request->month) : 'Semua Bulan',
            'total_employees' => $summaryQuery->count(),
            'total_bpjs_kesehatan' => $summaryQuery->sum('bpjs_kesehatan'),
            'total_bpjs_jht' => $summaryQuery->sum('bpjs_jht'),
            'total_bpjs_jp' => $summaryQuery->sum('bpjs_jp'),
        ];
        $summary['total_bpjs'] = $summary['total_bpjs_kesehatan'] + $summary['total_bpjs_jht'] + $summary['total_bpjs_jp'];
        $summary['total_bpjs_kesehatan_formatted'] = 'Rp ' . number_format($summary['total_bpjs_kesehatan'], 0, ',', '.');
        $summary['total_bpjs_jht_formatted'] = 'Rp ' . number_format($summary['total_bpjs_jht'], 0, ',', '.');
        $summary['total_bpjs_jp_formatted'] = 'Rp ' . number_format($summary['total_bpjs_jp'], 0, ',', '.');
        $summary['total_bpjs_formatted'] = 'Rp ' . number_format($summary['total_bpjs'], 0, ',', '.');

        // Monthly breakdown
        $monthlyBreakdown = PayrollSlip::query()
            ->select(
                DB::raw('payroll_periods.month as period_month'),
                DB::raw('SUM(payroll_slips.bpjs_kesehatan) as total_kesehatan'),
                DB::raw('SUM(payroll_slips.bpjs_jht) as total_jht'),
                DB::raw('SUM(payroll_slips.bpjs_jp) as total_jp'),
                DB::raw('COUNT(DISTINCT payroll_slips.id) as employee_count')
            )
            ->join('payroll_periods', 'payroll_slips.payroll_period_id', '=', 'payroll_periods.id')
            ->where('payroll_periods.year', $request->year)
            ->where(function ($q) {
                $q->where('payroll_slips.bpjs_kesehatan', '>', 0)
                    ->orWhere('payroll_slips.bpjs_jht', '>', 0)
                    ->orWhere('payroll_slips.bpjs_jp', '>', 0);
            })
            ->groupBy('payroll_periods.month')
            ->orderBy('payroll_periods.month')
            ->get()
            ->map(fn($item) => [
                'month' => (int) $item->period_month,
                'month_name' => $this->getMonthName((int) $item->period_month),
                'employee_count' => $item->employee_count,
                'total_kesehatan' => $item->total_kesehatan,
                'total_kesehatan_formatted' => 'Rp ' . number_format($item->total_kesehatan, 0, ',', '.'),
                'total_jht' => $item->total_jht,
                'total_jht_formatted' => 'Rp ' . number_format($item->total_jht, 0, ',', '.'),
                'total_jp' => $item->total_jp,
                'total_jp_formatted' => 'Rp ' . number_format($item->total_jp, 0, ',', '.'),
                'total' => $item->total_kesehatan + $item->total_jht + $item->total_jp,
                'total_formatted' => 'Rp ' . number_format($item->total_kesehatan + $item->total_jht + $item->total_jp, 0, ',', '.'),
            ]);

        // Paginated data
        $data = $query->orderByRaw('(bpjs_kesehatan + bpjs_jht + bpjs_jp) DESC')
            ->paginate($request->input('per_page', 15));

        $data->getCollection()->transform(fn($slip) => [
            'id' => $slip->id,
            'period' => $slip->period?->getPeriodLabel() ?? '-',
            'employee_name' => $slip->employee_name,
            'employee_identifier' => $slip->employee_identifier,
            'employee_type' => $slip->employee_type,
            'employee_type_label' => $slip->getEmployeeTypeLabel(),
            'gross_salary' => $slip->gross_salary,
            'gross_salary_formatted' => 'Rp ' . number_format($slip->gross_salary, 0, ',', '.'),
            'bpjs_kesehatan' => $slip->bpjs_kesehatan,
            'bpjs_kesehatan_formatted' => 'Rp ' . number_format($slip->bpjs_kesehatan, 0, ',', '.'),
            'bpjs_jht' => $slip->bpjs_jht,
            'bpjs_jht_formatted' => 'Rp ' . number_format($slip->bpjs_jht, 0, ',', '.'),
            'bpjs_jp' => $slip->bpjs_jp,
            'bpjs_jp_formatted' => 'Rp ' . number_format($slip->bpjs_jp, 0, ',', '.'),
            'total_bpjs' => $slip->bpjs_kesehatan + $slip->bpjs_jht + $slip->bpjs_jp,
            'total_bpjs_formatted' => 'Rp ' . number_format($slip->bpjs_kesehatan + $slip->bpjs_jht + $slip->bpjs_jp, 0, ',', '.'),
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
            'monthly_breakdown' => $monthlyBreakdown,
        ]);
    }

    /**
     * Export monthly payroll recap to CSV.
     */
    public function exportMonthlyRecap(Request $request): \Symfony\Component\HttpFoundation\StreamedResponse
    {
        $request->validate([
            'year' => 'required|integer|min:2000|max:2100',
        ]);

        $periods = PayrollPeriod::query()
            ->year($request->year)
            ->orderBy('month')
            ->get();

        $filename = 'rekap_gaji_' . $request->year . '_' . now()->format('Ymd_His') . '.csv';

        return response()->streamDownload(function () use ($periods) {
            $handle = fopen('php://output', 'w');

            fprintf($handle, chr(0xEF) . chr(0xBB) . chr(0xBF));

            fputcsv($handle, [
                'Bulan',
                'Tahun',
                'Status',
                'Jumlah Karyawan',
                'Total Gaji Kotor',
                'Total Potongan',
                'Total Gaji Bersih',
                'Tanggal Bayar',
            ], ';');

            foreach ($periods as $period) {
                fputcsv($handle, [
                    $this->getMonthName($period->month),
                    $period->year,
                    $period->getStatusLabel(),
                    $period->employee_count,
                    $period->total_gross,
                    $period->total_deductions,
                    $period->total_net,
                    $period->payment_date?->format('d/m/Y') ?? '-',
                ], ';');
            }

            fclose($handle);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    /**
     * Export PPh 21 report to CSV.
     */
    public function exportPph21(Request $request): \Symfony\Component\HttpFoundation\StreamedResponse
    {
        $request->validate([
            'year' => 'required|integer|min:2000|max:2100',
            'month' => 'nullable|integer|min:1|max:12',
        ]);

        $query = PayrollSlip::query()
            ->with(['period'])
            ->whereHas('period', function ($q) use ($request) {
                $q->year($request->year);
                if ($request->filled('month')) {
                    $q->month($request->month);
                }
            })
            ->where('pph21', '>', 0)
            ->orderBy('pph21', 'desc');

        $data = $query->get();

        $period = $request->month ? $this->getMonthName($request->month) . '_' . $request->year : $request->year;
        $filename = 'pph21_' . $period . '_' . now()->format('Ymd_His') . '.csv';

        return response()->streamDownload(function () use ($data) {
            $handle = fopen('php://output', 'w');

            fprintf($handle, chr(0xEF) . chr(0xBB) . chr(0xBF));

            fputcsv($handle, [
                'Periode',
                'NIK/NIP',
                'Nama',
                'Tipe',
                'Status PTKP',
                'Gaji Kotor',
                'PPh 21',
                'Gaji Bersih',
            ], ';');

            foreach ($data as $slip) {
                fputcsv($handle, [
                    $slip->period?->getPeriodLabel() ?? '-',
                    $slip->employee_identifier,
                    $slip->employee_name,
                    $slip->getEmployeeTypeLabel(),
                    $slip->ptkp_status,
                    $slip->gross_salary,
                    $slip->pph21,
                    $slip->net_salary,
                ], ';');
            }

            fclose($handle);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    /**
     * Export BPJS report to CSV.
     */
    public function exportBpjs(Request $request): \Symfony\Component\HttpFoundation\StreamedResponse
    {
        $request->validate([
            'year' => 'required|integer|min:2000|max:2100',
            'month' => 'nullable|integer|min:1|max:12',
        ]);

        $query = PayrollSlip::query()
            ->with(['period'])
            ->whereHas('period', function ($q) use ($request) {
                $q->year($request->year);
                if ($request->filled('month')) {
                    $q->month($request->month);
                }
            })
            ->where(function ($q) {
                $q->where('bpjs_kesehatan', '>', 0)
                    ->orWhere('bpjs_jht', '>', 0)
                    ->orWhere('bpjs_jp', '>', 0);
            })
            ->orderByRaw('(bpjs_kesehatan + bpjs_jht + bpjs_jp) DESC');

        $data = $query->get();

        $period = $request->month ? $this->getMonthName($request->month) . '_' . $request->year : $request->year;
        $filename = 'bpjs_' . $period . '_' . now()->format('Ymd_His') . '.csv';

        return response()->streamDownload(function () use ($data) {
            $handle = fopen('php://output', 'w');

            fprintf($handle, chr(0xEF) . chr(0xBB) . chr(0xBF));

            fputcsv($handle, [
                'Periode',
                'NIK/NIP',
                'Nama',
                'Tipe',
                'Gaji Kotor',
                'BPJS Kesehatan',
                'BPJS JHT',
                'BPJS JP',
                'Total BPJS',
            ], ';');

            foreach ($data as $slip) {
                $totalBpjs = $slip->bpjs_kesehatan + $slip->bpjs_jht + $slip->bpjs_jp;
                fputcsv($handle, [
                    $slip->period?->getPeriodLabel() ?? '-',
                    $slip->employee_identifier,
                    $slip->employee_name,
                    $slip->getEmployeeTypeLabel(),
                    $slip->gross_salary,
                    $slip->bpjs_kesehatan,
                    $slip->bpjs_jht,
                    $slip->bpjs_jp,
                    $totalBpjs,
                ], ';');
            }

            fclose($handle);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    /**
     * Get employee salary history report.
     */
    public function employeeHistory(Request $request, string $employeeId): JsonResponse
    {
        $request->validate([
            'employee_type' => 'required|in:teacher,staff',
        ]);

        $slips = PayrollSlip::query()
            ->with(['period'])
            ->where('employee_id', $employeeId)
            ->where('employee_type', $request->employee_type)
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(fn($slip) => [
                'id' => $slip->id,
                'period' => $slip->period?->getPeriodLabel() ?? '-',
                'period_id' => $slip->payroll_period_id,
                'base_salary' => $slip->base_salary,
                'base_salary_formatted' => 'Rp ' . number_format($slip->base_salary, 0, ',', '.'),
                'total_allowances' => $slip->total_allowances,
                'total_allowances_formatted' => 'Rp ' . number_format($slip->total_allowances, 0, ',', '.'),
                'gross_salary' => $slip->gross_salary,
                'gross_salary_formatted' => 'Rp ' . number_format($slip->gross_salary, 0, ',', '.'),
                'total_deductions' => $slip->total_deductions,
                'total_deductions_formatted' => 'Rp ' . number_format($slip->total_deductions, 0, ',', '.'),
                'net_salary' => $slip->net_salary,
                'net_salary_formatted' => 'Rp ' . number_format($slip->net_salary, 0, ',', '.'),
                'status' => $slip->status,
                'status_label' => $slip->getStatusLabel(),
            ]);

        $firstSlip = $slips->first();
        $summary = [
            'employee_name' => $firstSlip['employee_name'] ?? '-',
            'total_periods' => $slips->count(),
            'total_gross' => $slips->sum('gross_salary'),
            'total_net' => $slips->sum('net_salary'),
            'average_gross' => $slips->count() > 0 ? $slips->avg('gross_salary') : 0,
            'average_net' => $slips->count() > 0 ? $slips->avg('net_salary') : 0,
        ];
        $summary['total_gross_formatted'] = 'Rp ' . number_format($summary['total_gross'], 0, ',', '.');
        $summary['total_net_formatted'] = 'Rp ' . number_format($summary['total_net'], 0, ',', '.');
        $summary['average_gross_formatted'] = 'Rp ' . number_format($summary['average_gross'], 0, ',', '.');
        $summary['average_net_formatted'] = 'Rp ' . number_format($summary['average_net'], 0, ',', '.');

        return response()->json([
            'data' => $slips,
            'summary' => $summary,
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
