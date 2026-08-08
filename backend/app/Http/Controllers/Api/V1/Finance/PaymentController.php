<?php

namespace App\Http\Controllers\Api\V1\Finance;

use App\Http\Controllers\Api\ApiController;
use App\Http\Resources\Finance\PaymentResource;
use App\Infrastructure\Persistence\Eloquent\Finance\Payment;
use App\Infrastructure\Persistence\Eloquent\Finance\PaymentItem;
use App\Infrastructure\Persistence\Eloquent\Finance\PaymentMethod;
use App\Infrastructure\Persistence\Eloquent\Finance\StudentFee;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PaymentController extends ApiController
{
    /**
     * Display a listing of payments.
     */
    public function index(Request $request): JsonResponse
    {
        $query = Payment::query()
            ->with(['student.user', 'paymentMethod', 'receivedBy', 'verifiedBy'])
            ->withCount('items')
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
            ->when($request->student_id, fn($q, $id) => $q->where('student_id', $id))
            ->when($request->payment_method_id, fn($q, $id) => $q->where('payment_method_id', $id))
            ->when($request->from_date, fn($q, $date) => $q->whereDate('created_at', '>=', $date))
            ->when($request->to_date, fn($q, $date) => $q->whereDate('created_at', '<=', $date))
            ->when($request->has('needs_verification') && $request->boolean('needs_verification'), function ($q) {
                $q->needsVerification();
            });

        $sortField = $request->get('sort', 'created_at');
        $sortDirection = $request->get('direction', 'desc');
        $query->orderBy($sortField, $sortDirection);

        $perPage = $request->get('per_page', 15);
        $payments = $query->paginate($perPage);

        return $this->success(PaymentResource::collection($payments)->response()->getData(true));
    }

    /**
     * Store a newly created payment.
     */
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'student_id' => ['required', 'uuid', 'exists:students,id'],
            'payment_method_id' => ['required', 'uuid', 'exists:payment_methods,id'],
            'student_fee_ids' => ['required', 'array', 'min:1'],
            'student_fee_ids.*' => ['uuid', 'exists:student_fees,id'],
            'amounts' => ['required', 'array', 'min:1'],
            'amounts.*' => ['numeric', 'min:0'],
            'notes' => ['nullable', 'string', 'max:500'],
        ]);

        // Validate that fee IDs and amounts arrays have the same length
        if (count($data['student_fee_ids']) !== count($data['amounts'])) {
            return $this->error('Jumlah tagihan dan nominal pembayaran harus sama', 422);
        }

        // Get payment method for admin fee
        $paymentMethod = PaymentMethod::findOrFail($data['payment_method_id']);

        // Validate fees belong to the student and are unpaid
        $fees = StudentFee::whereIn('id', $data['student_fee_ids'])
            ->where('student_id', $data['student_id'])
            ->whereIn('status', [StudentFee::STATUS_UNPAID, StudentFee::STATUS_PARTIAL, StudentFee::STATUS_OVERDUE])
            ->get()
            ->keyBy('id');

        if ($fees->count() !== count($data['student_fee_ids'])) {
            return $this->error('Beberapa tagihan tidak valid atau sudah dibayar', 422);
        }

        // Validate amounts don't exceed remaining amounts
        foreach ($data['student_fee_ids'] as $index => $feeId) {
            $fee = $fees->get($feeId);
            $amount = $data['amounts'][$index];
            if ($amount > $fee->remaining_amount) {
                return $this->error("Nominal pembayaran melebihi sisa tagihan untuk {$fee->feeStructure?->feeType?->name}", 422);
            }
        }

        DB::beginTransaction();
        try {
            $totalAmount = array_sum($data['amounts']);
            $adminFee = $paymentMethod->admin_fee ?? 0;
            $grandTotal = $totalAmount + $adminFee;

            // Create payment
            $payment = Payment::create([
                'tenant_id' => auth()->user()->tenant_id,
                'student_id' => $data['student_id'],
                'payment_method_id' => $data['payment_method_id'],
                'total_amount' => $totalAmount,
                'admin_fee' => $adminFee,
                'grand_total' => $grandTotal,
                'status' => Payment::STATUS_PENDING,
                'notes' => $data['notes'] ?? null,
            ]);

            // Create payment items
            foreach ($data['student_fee_ids'] as $index => $feeId) {
                PaymentItem::create([
                    'payment_id' => $payment->id,
                    'student_fee_id' => $feeId,
                    'amount' => $data['amounts'][$index],
                ]);
            }

            DB::commit();

            $payment->load(['student.user', 'paymentMethod', 'items.studentFee.feeStructure.feeType']);

            return $this->success(
                new PaymentResource($payment),
                'Pembayaran berhasil dibuat',
                201
            );
        } catch (\Exception $e) {
            DB::rollBack();
            return $this->error('Gagal membuat pembayaran: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Display the specified payment.
     */
    public function show(Payment $payment): JsonResponse
    {
        $payment->load([
            'student.user',
            'student.currentClass',
            'paymentMethod',
            'receivedBy',
            'verifiedBy',
            'items.studentFee.feeStructure.feeType',
        ]);

        return $this->success(new PaymentResource($payment));
    }

    /**
     * Complete payment (for cash payments).
     */
    public function complete(Request $request, Payment $payment): JsonResponse
    {
        if (!$payment->isPending()) {
            return $this->error('Pembayaran sudah diproses atau dibatalkan', 422);
        }

        $data = $request->validate([
            'transaction_id' => ['nullable', 'string', 'max:100'],
            'notes' => ['nullable', 'string', 'max:500'],
        ]);

        DB::beginTransaction();
        try {
            $payment->markAsCompleted(
                auth()->id(),
                $data['transaction_id'] ?? null
            );

            if (!empty($data['notes'])) {
                $payment->update(['notes' => $data['notes']]);
            }

            DB::commit();

            $payment->load(['student.user', 'paymentMethod', 'items.studentFee']);

            return $this->success(new PaymentResource($payment), 'Pembayaran berhasil dicatat');
        } catch (\Exception $e) {
            DB::rollBack();
            return $this->error('Gagal mencatat pembayaran: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Upload payment proof (for transfer payments).
     */
    public function uploadProof(Request $request, Payment $payment): JsonResponse
    {
        if (!$payment->isPending()) {
            return $this->error('Pembayaran sudah diproses atau dibatalkan', 422);
        }

        $data = $request->validate([
            'payment_proof' => ['required', 'file', 'image', 'max:5120'], // 5MB max
            'transaction_id' => ['nullable', 'string', 'max:100'],
        ]);

        // Store the file
        $path = $request->file('payment_proof')->store('payment-proofs', 'public');

        $payment->update([
            'status' => Payment::STATUS_PROCESSING,
            'payment_proof' => $path,
            'transaction_id' => $data['transaction_id'] ?? null,
        ]);

        return $this->success(new PaymentResource($payment), 'Bukti pembayaran berhasil diunggah');
    }

    /**
     * Verify a payment (for transfer payments).
     */
    public function verify(Request $request, Payment $payment): JsonResponse
    {
        if (!$payment->canBeVerified()) {
            return $this->error('Pembayaran tidak dapat diverifikasi', 422);
        }

        $data = $request->validate([
            'approved' => ['required', 'boolean'],
            'notes' => ['nullable', 'string', 'max:500'],
        ]);

        DB::beginTransaction();
        try {
            if ($data['approved']) {
                $payment->verify(auth()->id());
                $payment->markAsCompleted(auth()->id(), $payment->transaction_id);
                $message = 'Pembayaran berhasil diverifikasi';
            } else {
                $payment->update([
                    'status' => Payment::STATUS_FAILED,
                    'verified_by' => auth()->id(),
                    'verified_at' => now(),
                    'notes' => $data['notes'] ?? 'Verifikasi ditolak',
                ]);
                $message = 'Pembayaran ditolak';
            }

            DB::commit();

            $payment->load(['student.user', 'paymentMethod', 'items.studentFee']);

            return $this->success(new PaymentResource($payment), $message);
        } catch (\Exception $e) {
            DB::rollBack();
            return $this->error('Gagal memverifikasi pembayaran: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Cancel a payment.
     */
    public function cancel(Request $request, Payment $payment): JsonResponse
    {
        if (!$payment->canBeCancelled()) {
            return $this->error('Pembayaran tidak dapat dibatalkan', 422);
        }

        $data = $request->validate([
            'reason' => ['nullable', 'string', 'max:500'],
        ]);

        $payment->cancel();

        if (!empty($data['reason'])) {
            $payment->update(['notes' => $data['reason']]);
        }

        return $this->success(null, 'Pembayaran berhasil dibatalkan');
    }

    /**
     * Get receipt data for a completed payment.
     */
    public function receipt(Payment $payment): JsonResponse
    {
        if (!$payment->isCompleted()) {
            return $this->error('Kuitansi hanya tersedia untuk pembayaran yang sudah selesai', 422);
        }

        $payment->load([
            'student.user',
            'student.currentClass.gradeLevel',
            'paymentMethod',
            'receivedBy',
            'items.studentFee.feeStructure.feeType',
            'items.studentFee.academicYear',
        ]);

        $receiptData = [
            'invoice_number' => $payment->invoice_number,
            'date' => $payment->paid_at?->format('d F Y'),
            'time' => $payment->paid_at?->format('H:i'),
            'student' => [
                'nis' => $payment->student->nis,
                'name' => $payment->student->user?->full_name ?? $payment->student->full_name,
                'class' => $payment->student->currentClass?->name,
                'grade' => $payment->student->currentClass?->gradeLevel?->name,
            ],
            'items' => $payment->items->map(fn($item) => [
                'description' => $item->studentFee->feeStructure?->feeType?->name . ' - ' .
                    $this->getPeriodLabel($item->studentFee->month, $item->studentFee->year),
                'amount' => (float) $item->amount,
                'amount_formatted' => 'Rp ' . number_format($item->amount, 0, ',', '.'),
            ]),
            'subtotal' => (float) $payment->total_amount,
            'subtotal_formatted' => 'Rp ' . number_format($payment->total_amount, 0, ',', '.'),
            'admin_fee' => (float) $payment->admin_fee,
            'admin_fee_formatted' => 'Rp ' . number_format($payment->admin_fee, 0, ',', '.'),
            'grand_total' => (float) $payment->grand_total,
            'grand_total_formatted' => 'Rp ' . number_format($payment->grand_total, 0, ',', '.'),
            'payment_method' => $payment->paymentMethod?->name,
            'transaction_id' => $payment->transaction_id,
            'received_by' => $payment->receivedBy?->full_name,
        ];

        return $this->success($receiptData);
    }

    /**
     * Get payment summary statistics.
     */
    public function summary(Request $request): JsonResponse
    {
        $query = Payment::query()
            ->when($request->from_date, fn($q, $date) => $q->whereDate('created_at', '>=', $date))
            ->when($request->to_date, fn($q, $date) => $q->whereDate('created_at', '<=', $date));

        $summary = [
            'total_received' => (clone $query)->completed()->sum('grand_total'),
            'total_pending' => (clone $query)->pending()->sum('grand_total'),
            'total_admin_fees' => (clone $query)->completed()->sum('admin_fee'),
            'count_total' => (clone $query)->count(),
            'count_completed' => (clone $query)->completed()->count(),
            'count_pending' => (clone $query)->pending()->count(),
            'count_failed' => (clone $query)->where('status', Payment::STATUS_FAILED)->count(),
            'count_cancelled' => (clone $query)->where('status', Payment::STATUS_CANCELLED)->count(),
            'count_needs_verification' => (clone $query)->needsVerification()->count(),
        ];

        $summary['total_received_formatted'] = 'Rp ' . number_format($summary['total_received'], 0, ',', '.');
        $summary['total_pending_formatted'] = 'Rp ' . number_format($summary['total_pending'], 0, ',', '.');

        return $this->success($summary);
    }

    /**
     * Get available statuses.
     */
    public function statuses(): JsonResponse
    {
        return $this->success(Payment::getStatuses());
    }

    /**
     * Get period label.
     */
    private function getPeriodLabel(?int $month, ?int $year): string
    {
        if (!$month || !$year) {
            return '';
        }

        $months = [
            1 => 'Jan', 2 => 'Feb', 3 => 'Mar', 4 => 'Apr',
            5 => 'Mei', 6 => 'Jun', 7 => 'Jul', 8 => 'Agu',
            9 => 'Sep', 10 => 'Okt', 11 => 'Nov', 12 => 'Des',
        ];

        return ($months[$month] ?? $month) . ' ' . $year;
    }
}
