<?php

namespace App\Domain\Payroll\Services;

use App\Infrastructure\Persistence\Eloquent\Payroll\PayrollSlip;
use App\Infrastructure\Persistence\Eloquent\Payroll\PayrollPeriod;
use App\Models\Tenant;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use ZipArchive;

class PayrollSlipPdfService
{
    /**
     * Generate PDF untuk satu slip gaji.
     */
    public function generateSingle(PayrollSlip $slip): \Barryvdh\DomPDF\PDF
    {
        $slip->load(['period', 'items']);

        $tenant = $this->getTenant($slip);
        $data = $this->prepareSlipData($slip, $tenant);

        return Pdf::loadView('pdf.payroll-slip', $data)
            ->setPaper('a4', 'portrait');
    }

    /**
     * Generate PDF dan langsung download.
     */
    public function download(PayrollSlip $slip): \Symfony\Component\HttpFoundation\Response
    {
        $pdf = $this->generateSingle($slip);
        $filename = $this->generateFilename($slip);

        return $pdf->download($filename);
    }

    /**
     * Generate PDF dan return sebagai stream untuk preview.
     */
    public function stream(PayrollSlip $slip): \Symfony\Component\HttpFoundation\Response
    {
        $pdf = $this->generateSingle($slip);
        $filename = $this->generateFilename($slip);

        return $pdf->stream($filename);
    }

    /**
     * Generate bulk PDF untuk satu periode dan return sebagai ZIP.
     */
    public function generateBulkZip(PayrollPeriod $period, ?string $employeeType = null): string
    {
        $query = $period->slips()->with('items');

        if ($employeeType) {
            $query->where('employee_type', $employeeType);
        }

        $slips = $query->get();
        $tenant = $this->getTenantFromPeriod($period);

        // Create temp directory for PDFs
        $tempDir = storage_path('app/temp/payroll-pdf-' . uniqid());
        if (!is_dir($tempDir)) {
            mkdir($tempDir, 0755, true);
        }

        // Generate individual PDFs
        foreach ($slips as $slip) {
            $data = $this->prepareSlipData($slip, $tenant);
            $pdf = Pdf::loadView('pdf.payroll-slip', $data)->setPaper('a4', 'portrait');

            $filename = $this->generateFilename($slip);
            $pdf->save($tempDir . '/' . $filename);
        }

        // Create ZIP file
        $zipFilename = sprintf(
            'slip-gaji-%s-%04d%02d.zip',
            $tenant?->slug ?? 'all',
            $period->year,
            $period->month
        );
        $zipPath = storage_path('app/temp/' . $zipFilename);

        $zip = new ZipArchive();
        $zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE);

        $files = glob($tempDir . '/*.pdf');
        foreach ($files as $file) {
            $zip->addFile($file, basename($file));
        }

        $zip->close();

        // Cleanup temp PDFs
        array_map('unlink', $files);
        rmdir($tempDir);

        return $zipPath;
    }

    /**
     * Prepare data untuk view PDF.
     */
    private function prepareSlipData(PayrollSlip $slip, ?Tenant $tenant): array
    {
        $earnings = $slip->items->where('type', 'earning')->sortBy('component_code');
        $deductions = $slip->items->where('type', 'deduction')->sortBy('component_code');

        // Format periode
        $periodLabel = $this->formatPeriodLabel($slip->period);

        return [
            'slip' => $slip,
            'period' => $slip->period,
            'periodLabel' => $periodLabel,
            'tenant' => $tenant,
            'earnings' => $earnings,
            'deductions' => $deductions,
            'logoUrl' => $this->getLogoUrl($tenant),
            'generatedAt' => now()->format('d M Y H:i'),
        ];
    }

    /**
     * Format label periode (contoh: "September 2024").
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

    /**
     * Generate filename untuk PDF.
     */
    private function generateFilename(PayrollSlip $slip): string
    {
        $identifier = $slip->employee_identifier ?? $slip->id;
        $name = preg_replace('/[^a-zA-Z0-9]/', '-', $slip->employee_name);

        return sprintf(
            'slip-%04d%02d-%s-%s.pdf',
            $slip->period->year,
            $slip->period->month,
            $identifier,
            $name
        );
    }

    /**
     * Get tenant dari slip.
     */
    private function getTenant(PayrollSlip $slip): ?Tenant
    {
        $slip->load('period');
        return $slip->period?->tenant_id
            ? Tenant::find($slip->period->tenant_id)
            : tenant();
    }

    /**
     * Get tenant dari period.
     */
    private function getTenantFromPeriod(PayrollPeriod $period): ?Tenant
    {
        return $period->tenant_id
            ? Tenant::find($period->tenant_id)
            : tenant();
    }

    /**
     * Get logo URL untuk PDF.
     */
    private function getLogoUrl(?Tenant $tenant): ?string
    {
        if (!$tenant || !$tenant->logo) {
            return null;
        }

        // Return base64 encoded image for PDF compatibility
        $logoPath = Storage::disk('public')->path($tenant->logo);

        if (file_exists($logoPath)) {
            $type = pathinfo($logoPath, PATHINFO_EXTENSION);
            $data = file_get_contents($logoPath);
            return 'data:image/' . $type . ';base64,' . base64_encode($data);
        }

        return null;
    }
}
