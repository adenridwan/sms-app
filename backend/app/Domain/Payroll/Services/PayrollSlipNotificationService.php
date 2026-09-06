<?php

namespace App\Domain\Payroll\Services;

use App\Domain\Notification\Services\WhatsAppService;
use App\Infrastructure\Persistence\Eloquent\Payroll\PayrollPeriod;
use App\Infrastructure\Persistence\Eloquent\Payroll\PayrollSlip;
use App\Models\Tenant;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

class PayrollSlipNotificationService
{
    public function __construct(
        private WhatsAppService $whatsAppService,
        private PayrollSlipPdfService $pdfService
    ) {}

    /**
     * Kirim slip gaji ke WhatsApp untuk satu karyawan.
     */
    public function sendSingle(PayrollSlip $slip, ?string $customPhone = null): array
    {
        $slip->load(['period', 'items']);

        // Get phone number
        $phone = $customPhone ?? $this->getEmployeePhone($slip);

        if (!$phone) {
            return [
                'success' => false,
                'message' => 'Nomor telepon tidak ditemukan',
            ];
        }

        // Initialize WhatsApp service
        $tenantId = $slip->period?->tenant_id ?? tenant()?->id;
        if (!$tenantId) {
            return [
                'success' => false,
                'message' => 'Tenant tidak ditemukan',
            ];
        }

        $this->whatsAppService->initializeForTenant($tenantId);

        if (!$this->whatsAppService->isAvailable()) {
            return [
                'success' => false,
                'message' => 'WhatsApp belum dikonfigurasi. Silakan atur di menu Pengaturan > Notifikasi.',
            ];
        }

        // Build message
        $message = $this->buildMessage($slip);

        // Send message
        $sent = $this->whatsAppService->send($phone, $message);

        if ($sent) {
            Log::info('Payroll slip sent via WhatsApp', [
                'slip_id' => $slip->id,
                'employee' => $slip->employee_name,
                'phone' => $this->maskPhone($phone),
            ]);

            return [
                'success' => true,
                'message' => 'Slip gaji berhasil dikirim ke ' . $this->maskPhone($phone),
            ];
        }

        return [
            'success' => false,
            'message' => 'Gagal mengirim pesan WhatsApp',
        ];
    }

    /**
     * Kirim slip gaji ke semua karyawan dalam satu periode.
     */
    public function sendBulk(PayrollPeriod $period, ?string $employeeType = null): array
    {
        $query = $period->slips()->with(['items', 'teacher.user.profile', 'staff.user.profile']);

        if ($employeeType) {
            $query->where('employee_type', $employeeType);
        }

        $slips = $query->get();

        // Initialize WhatsApp service
        $tenantId = $period->tenant_id ?? tenant()?->id;
        if (!$tenantId) {
            return [
                'success' => false,
                'sent' => 0,
                'failed' => 0,
                'errors' => [['message' => 'Tenant tidak ditemukan']],
            ];
        }

        $this->whatsAppService->initializeForTenant($tenantId);

        if (!$this->whatsAppService->isAvailable()) {
            return [
                'success' => false,
                'sent' => 0,
                'failed' => 0,
                'errors' => [['message' => 'WhatsApp belum dikonfigurasi']],
            ];
        }

        $sent = 0;
        $failed = 0;
        $errors = [];
        $noPhone = [];

        foreach ($slips as $slip) {
            $phone = $this->getEmployeePhone($slip);

            if (!$phone) {
                $noPhone[] = $slip->employee_name;
                $failed++;
                continue;
            }

            $message = $this->buildMessage($slip);
            $result = $this->whatsAppService->send($phone, $message);

            if ($result) {
                $sent++;
            } else {
                $failed++;
                $errors[] = [
                    'employee' => $slip->employee_name,
                    'message' => 'Gagal mengirim',
                ];
            }

            // Small delay to avoid rate limiting
            usleep(500000); // 0.5 second
        }

        Log::info('Bulk payroll slip notification completed', [
            'period_id' => $period->id,
            'sent' => $sent,
            'failed' => $failed,
        ]);

        return [
            'success' => $sent > 0,
            'sent' => $sent,
            'failed' => $failed,
            'no_phone' => $noPhone,
            'errors' => $errors,
            'message' => "Berhasil mengirim {$sent} dari " . ($sent + $failed) . " slip gaji",
        ];
    }

    /**
     * Preview penerima untuk bulk send.
     */
    public function previewRecipients(PayrollPeriod $period, ?string $employeeType = null): array
    {
        $query = $period->slips()->with(['teacher.user.profile', 'staff.user.profile']);

        if ($employeeType) {
            $query->where('employee_type', $employeeType);
        }

        $slips = $query->get();

        $recipients = [];
        $noPhone = [];

        foreach ($slips as $slip) {
            $phone = $this->getEmployeePhone($slip);

            if ($phone) {
                $recipients[] = [
                    'id' => $slip->id,
                    'name' => $slip->employee_name,
                    'type' => $slip->employee_type,
                    'type_label' => $slip->getEmployeeTypeLabel(),
                    'phone' => $this->maskPhone($phone),
                    'net_salary' => $slip->net_salary,
                    'net_salary_formatted' => 'Rp ' . number_format($slip->net_salary, 0, ',', '.'),
                ];
            } else {
                $noPhone[] = [
                    'id' => $slip->id,
                    'name' => $slip->employee_name,
                    'type' => $slip->employee_type,
                    'type_label' => $slip->getEmployeeTypeLabel(),
                ];
            }
        }

        return [
            'recipients' => $recipients,
            'no_phone' => $noPhone,
            'total' => count($recipients),
            'total_no_phone' => count($noPhone),
        ];
    }

    /**
     * Get employee phone number from slip.
     */
    private function getEmployeePhone(PayrollSlip $slip): ?string
    {
        $employee = $slip->employee_type === 'teacher'
            ? $slip->teacher
            : $slip->staff;

        $phone = $employee?->user?->profile?->phone;

        if (!$phone) {
            return null;
        }

        return $this->normalizePhone($phone);
    }

    /**
     * Normalize phone number to international format.
     */
    private function normalizePhone(string $phone): string
    {
        // Remove all non-digit characters
        $phone = preg_replace('/[^0-9]/', '', $phone);

        // Convert 08xx to 628xx
        if (str_starts_with($phone, '0')) {
            $phone = '62' . substr($phone, 1);
        }

        // Add 62 if not present
        if (!str_starts_with($phone, '62')) {
            $phone = '62' . $phone;
        }

        return $phone;
    }

    /**
     * Mask phone number for display.
     */
    private function maskPhone(string $phone): string
    {
        if (strlen($phone) < 8) {
            return $phone;
        }

        return substr($phone, 0, 4) . '****' . substr($phone, -4);
    }

    /**
     * Build WhatsApp message for slip.
     */
    private function buildMessage(PayrollSlip $slip): string
    {
        $periodLabel = $this->formatPeriodLabel($slip->period);
        $tenant = $slip->period?->tenant_id
            ? Tenant::find($slip->period->tenant_id)
            : tenant();

        $tenantName = $tenant?->name ?? 'Sekolah';

        // Format attendance if available
        $attendanceInfo = '';
        if ($slip->working_days > 0) {
            $attendanceInfo = "\n\n*Kehadiran*\n" .
                "Hari Kerja: {$slip->working_days}\n" .
                "Hadir: {$slip->days_present} hari\n" .
                "Tidak Hadir: {$slip->days_absent} hari";
        }

        // Format earnings
        $earnings = $slip->items->where('type', 'earning');
        $earningsText = "";
        foreach ($earnings as $item) {
            $amount = number_format($item->amount, 0, ',', '.');
            $earningsText .= "• {$item->component_name}: Rp {$amount}\n";
        }

        // Format deductions
        $deductions = $slip->items->where('type', 'deduction');
        $deductionsText = "";
        foreach ($deductions as $item) {
            $amount = number_format($item->amount, 0, ',', '.');
            $deductionsText .= "• {$item->component_name}: Rp {$amount}\n";
        }

        $grossFormatted = number_format($slip->gross_salary, 0, ',', '.');
        $deductionsFormatted = number_format($slip->total_deductions, 0, ',', '.');
        $netFormatted = number_format($slip->net_salary, 0, ',', '.');

        return <<<MESSAGE
*SLIP GAJI - {$periodLabel}*
{$tenantName}
━━━━━━━━━━━━━━━━━━━━━

Kepada Yth,
*{$slip->employee_name}*
{$slip->employee_identifier}{$attendanceInfo}

*Pendapatan*
{$earningsText}Total: Rp {$grossFormatted}

*Potongan*
{$deductionsText}Total: Rp {$deductionsFormatted}

━━━━━━━━━━━━━━━━━━━━━
*GAJI BERSIH: Rp {$netFormatted}*
━━━━━━━━━━━━━━━━━━━━━

_Pesan ini dikirim otomatis oleh sistem._
_Hubungi bagian keuangan untuk pertanyaan._
MESSAGE;
    }

    /**
     * Format period label.
     */
    private function formatPeriodLabel(PayrollPeriod $period): string
    {
        $months = [
            1 => 'Januari', 2 => 'Februari', 3 => 'Maret',
            4 => 'April', 5 => 'Mei', 6 => 'Juni',
            7 => 'Juli', 8 => 'Agustus', 9 => 'September',
            10 => 'Oktober', 11 => 'November', 12 => 'Desember',
        ];

        return ($months[$period->month] ?? $period->month) . ' ' . $period->year;
    }
}
