<?php

namespace App\Http\Controllers\Api\V1\Attendance;

use App\Domain\Attendance\Services\QrCodeGeneratorService;
use App\Domain\Attendance\Services\QrExportService;
use App\Exports\Attendance\QrCodeExport;
use App\Http\Controllers\Api\ApiController;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Export QR/RFID untuk pembuatan kartu di luar aplikasi (vendor/desainer).
 * Format: Excel (A), ZIP gambar (B), PDF kartu (C). Scope: kelas/semua siswa,
 * atau semua guru. RFID bisa di-generate otomatis untuk yang belum punya.
 */
class QrExportController extends ApiController
{
    public function __construct(
        private QrExportService $exporter,
        private QrCodeGeneratorService $qr,
    ) {}

    public function export(Request $request)
    {
        $data = $request->validate([
            'type' => ['required', 'in:student,teacher'],
            'scope' => ['required_if:type,student', 'in:class,all'],
            'classroom_id' => ['required_if:scope,class', 'uuid', 'exists:classrooms,id'],
            'format' => ['required', 'in:excel,zip,pdf'],
            'image' => ['sometimes', 'in:svg,png,both'],
            'generate_rfid' => ['sometimes', 'boolean'],
        ]);

        $generateRfid = (bool) ($data['generate_rfid'] ?? true);
        $image = $data['image'] ?? 'both';

        $rows = $data['type'] === 'teacher'
            ? $this->exporter->teacherRows($generateRfid)
            : $this->exporter->studentRows($data['scope'], $data['classroom_id'] ?? null, $generateRfid);

        if ($rows->isEmpty()) {
            return $this->error('Tidak ada data untuk diexport.', 422);
        }

        $baseName = 'qr-' . $data['type'] . '-' . now()->format('Ymd-His');

        return match ($data['format']) {
            'excel' => Excel::download(new QrCodeExport($rows), $baseName . '.xlsx'),
            'zip' => $this->downloadZip($rows, $image, $baseName),
            'pdf' => $this->downloadPdf($rows, $data['type']),
        };
    }

    /**
     * Generate kode RFID otomatis untuk siswa/guru yang belum punya.
     */
    public function generateRfid(Request $request)
    {
        $data = $request->validate([
            'type' => ['required', 'in:student,teacher'],
            'scope' => ['required_if:type,student', 'in:class,all'],
            'classroom_id' => ['required_if:scope,class', 'uuid', 'exists:classrooms,id'],
        ]);

        $rows = $data['type'] === 'teacher'
            ? $this->exporter->teacherRows(true)
            : $this->exporter->studentRows($data['scope'], $data['classroom_id'] ?? null, true);

        $withRfid = $rows->filter(fn ($r) => ! empty($r['rfid_code']))->count();

        return $this->success([
            'total' => $rows->count(),
            'with_rfid' => $withRfid,
        ], 'Kode RFID berhasil digenerate untuk yang belum punya.');
    }

    /**
     * ZIP berisi gambar QR (SVG dan/atau PNG) per orang.
     */
    private function downloadZip(Collection $rows, string $image, string $baseName): StreamedResponse
    {
        $tmp = tempnam(sys_get_temp_dir(), 'qrzip');
        $zip = new \ZipArchive();
        $zip->open($tmp, \ZipArchive::OVERWRITE);

        foreach ($rows as $row) {
            $fileBase = $this->safeName($row['identifier'] . '_' . $row['name']);

            if ($image === 'svg' || $image === 'both') {
                $zip->addFromString($fileBase . '.svg', $this->qr->generateQrSvg($row['unique_code'], 400));
            }
            if ($image === 'png' || $image === 'both') {
                $zip->addFromString($fileBase . '.png', $this->qr->generateQrPng($row['unique_code'], 512));
            }
        }

        // Sertakan daftar kode agar vendor punya pemetaan file → identitas.
        $zip->addFromString('daftar-kode.csv', $this->rowsToCsv($rows));
        $zip->close();

        return response()->streamDownload(function () use ($tmp) {
            readfile($tmp);
            @unlink($tmp);
        }, $baseName . '.zip', ['Content-Type' => 'application/zip']);
    }

    /**
     * PDF lembar kartu (QR PNG + identitas).
     */
    private function downloadPdf(Collection $rows, string $type)
    {
        $rows = $rows->map(function ($row) {
            $row['qr_png'] = 'data:image/png;base64,' . base64_encode(
                $this->qr->generateQrPng($row['unique_code'], 300)
            );
            return $row;
        });

        $pdf = Pdf::loadView('exports.qr-cards', [
            'rows' => $rows,
            'title' => $type === 'teacher' ? 'Kartu QR Guru' : 'Kartu QR Siswa',
        ])->setPaper('a4', 'portrait');

        return $pdf->download('qr-cards-' . $type . '-' . now()->format('Ymd-His') . '.pdf');
    }

    private function rowsToCsv(Collection $rows): string
    {
        $lines = ['tipe,identifier,nama,kelas,kode_qr,kode_rfid'];
        foreach ($rows as $r) {
            $lines[] = implode(',', array_map(
                fn ($v) => '"' . str_replace('"', '""', (string) $v) . '"',
                [$r['kind'], $r['identifier'], $r['name'], $r['classroom'], $r['unique_code'], $r['rfid_code']]
            ));
        }

        return implode("\n", $lines);
    }

    private function safeName(string $name): string
    {
        return Str::of($name)->trim()->replaceMatches('/[^\p{L}\p{N}_-]+/u', '_')->limit(60, '');
    }
}
