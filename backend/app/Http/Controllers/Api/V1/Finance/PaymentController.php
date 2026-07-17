<?php

namespace App\Http\Controllers\Api\V1\Finance;

use App\Http\Controllers\Api\ApiController;
use App\Http\Resources\PaymentResource;
use App\Models\Finance\Payment;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class PaymentController extends ApiController
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request): JsonResponse
    {
        $query = Payment::with(['student.user', 'feeType'])
            ->when($request->search, function ($q, $search) {
                $q->where('invoice_number', 'ilike', "%{$search}%")
                    ->orWhereHas('student', function ($q) use ($search) {
                        $q->where('nis', 'ilike', "%{$search}%")
                            ->orWhereHas('user', function ($q) use ($search) {
                                $q->where('first_name', 'ilike', "%{$search}%")
                                    ->orWhere('last_name', 'ilike', "%{$search}%");
                            });
                    });
            })
            ->when($request->status, fn($q, $status) => $q->where('status', $status))
            ->when($request->student_id, fn($q, $studentId) => $q->where('student_id', $studentId))
            ->when($request->fee_type_id, fn($q, $feeTypeId) => $q->where('fee_type_id', $feeTypeId))
            ->when($request->academic_year_id, fn($q, $yearId) => $q->where('academic_year_id', $yearId))
            ->when($request->semester_id, fn($q, $semesterId) => $q->where('semester_id', $semesterId))
            ->when($request->from_date, fn($q, $date) => $q->whereDate('created_at', '>=', $date))
            ->when($request->to_date, fn($q, $date) => $q->whereDate('created_at', '<=', $date));

        $sortField = $request->get('sort', 'created_at');
        $sortDirection = $request->get('direction', 'desc');
        $query->orderBy($sortField, $sortDirection);

        $perPage = $request->get('per_page', 15);
        $payments = $query->paginate($perPage);

        return $this->success(PaymentResource::collection($payments)->response()->getData(true));
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'student_id' => ['required', 'uuid', 'exists:students,id'],
            'fee_type_id' => ['required', 'uuid', 'exists:fee_types,id'],
            'amount' => ['required', 'numeric', 'min:0'],
            'discount' => ['nullable', 'numeric', 'min:0'],
            'due_date' => ['required', 'date'],
            'academic_year_id' => ['required', 'uuid', 'exists:academic_years,id'],
            'semester_id' => ['nullable', 'uuid', 'exists:semesters,id'],
            'notes' => ['nullable', 'string', 'max:500'],
        ]);

        $data['invoice_number'] = $this->generateInvoiceNumber();
        $data['status'] = 'pending';

        $payment = Payment::create($data);
        $payment->load(['student.user', 'feeType']);

        return $this->success(
            new PaymentResource($payment),
            'Tagihan berhasil dibuat',
            201
        );
    }

    /**
     * Display the specified resource.
     */
    public function show(Payment $payment): JsonResponse
    {
        $payment->load(['student.user', 'feeType', 'academicYear', 'semester']);

        return $this->success(new PaymentResource($payment));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Payment $payment): JsonResponse
    {
        if ($payment->status === 'paid') {
            return $this->error('Tagihan yang sudah dibayar tidak dapat diubah', 422);
        }

        $data = $request->validate([
            'amount' => ['sometimes', 'numeric', 'min:0'],
            'discount' => ['nullable', 'numeric', 'min:0'],
            'due_date' => ['sometimes', 'date'],
            'notes' => ['nullable', 'string', 'max:500'],
        ]);

        $payment->update($data);
        $payment->load(['student.user', 'feeType']);

        return $this->success(new PaymentResource($payment), 'Tagihan berhasil diperbarui');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Payment $payment): JsonResponse
    {
        if ($payment->status === 'paid') {
            return $this->error('Tagihan yang sudah dibayar tidak dapat dihapus', 422);
        }

        $payment->delete();

        return $this->success(null, 'Tagihan berhasil dihapus');
    }

    /**
     * Process payment.
     */
    public function pay(Request $request, Payment $payment): JsonResponse
    {
        if ($payment->status === 'paid') {
            return $this->error('Tagihan sudah dibayar', 422);
        }

        $data = $request->validate([
            'payment_method' => ['required', 'string', 'in:cash,transfer,qris,virtual_account'],
            'payment_reference' => ['nullable', 'string', 'max:100'],
            'notes' => ['nullable', 'string', 'max:500'],
        ]);

        $payment->update([
            'status' => 'paid',
            'paid_at' => now(),
            'payment_method' => $data['payment_method'],
            'payment_reference' => $data['payment_reference'] ?? null,
            'notes' => $data['notes'] ?? $payment->notes,
        ]);

        $payment->load(['student.user', 'feeType']);

        return $this->success(new PaymentResource($payment), 'Pembayaran berhasil dicatat');
    }

    /**
     * Cancel payment.
     */
    public function cancel(Request $request, Payment $payment): JsonResponse
    {
        if ($payment->status === 'paid') {
            return $this->error('Tagihan yang sudah dibayar tidak dapat dibatalkan', 422);
        }

        $payment->update([
            'status' => 'cancelled',
            'notes' => $request->input('reason', 'Dibatalkan'),
        ]);

        return $this->success(null, 'Tagihan berhasil dibatalkan');
    }

    /**
     * Get payment summary.
     */
    public function summary(Request $request): JsonResponse
    {
        $academicYearId = $request->academic_year_id;
        $semesterId = $request->semester_id;

        $query = Payment::query()
            ->when($academicYearId, fn($q) => $q->where('academic_year_id', $academicYearId))
            ->when($semesterId, fn($q) => $q->where('semester_id', $semesterId));

        $summary = [
            'total_billed' => (clone $query)->sum('amount'),
            'total_discount' => (clone $query)->sum('discount'),
            'total_paid' => (clone $query)->where('status', 'paid')->sum(DB::raw('amount - COALESCE(discount, 0)')),
            'total_pending' => (clone $query)->where('status', 'pending')->sum(DB::raw('amount - COALESCE(discount, 0)')),
            'total_overdue' => (clone $query)->where('status', 'overdue')->sum(DB::raw('amount - COALESCE(discount, 0)')),
            'count_paid' => (clone $query)->where('status', 'paid')->count(),
            'count_pending' => (clone $query)->where('status', 'pending')->count(),
            'count_overdue' => (clone $query)->where('status', 'overdue')->count(),
        ];

        return $this->success($summary);
    }

    /**
     * Generate invoice number.
     */
    private function generateInvoiceNumber(): string
    {
        $prefix = 'INV';
        $date = now()->format('Ymd');
        $random = strtoupper(Str::random(4));

        return "{$prefix}-{$date}-{$random}";
    }
}
