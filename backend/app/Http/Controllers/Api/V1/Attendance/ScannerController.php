<?php

namespace App\Http\Controllers\Api\V1\Attendance;

use App\Domain\Attendance\Enums\ScanType;
use App\Domain\Attendance\Services\AttendanceScanService;
use App\Http\Controllers\Api\ApiController;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ScannerController extends ApiController
{
    public function __construct(
        private AttendanceScanService $scanService
    ) {}

    /**
     * Get bootstrap data for scanner app
     */
    public function bootstrap(Request $request): JsonResponse
    {
        $tenantId = $request->user()->tenant_id;

        $data = $this->scanService->getBootstrapData($tenantId);

        return $this->success($data, 'Bootstrap data loaded');
    }

    /**
     * Process a scan (check-in or check-out)
     */
    public function scan(Request $request): JsonResponse
    {
        $data = $request->validate([
            'unique_code' => ['required', 'string', 'max:100'],
            'waktu' => ['required', 'in:masuk,pulang'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
        ]);

        $scanType = ScanType::from($data['waktu']);

        $location = null;
        if (isset($data['latitude']) && isset($data['longitude'])) {
            $location = [
                'latitude' => $data['latitude'],
                'longitude' => $data['longitude'],
            ];
        }

        $result = $this->scanService->processScan(
            $data['unique_code'],
            $scanType,
            $location
        );

        if ($result['success']) {
            return $this->success($result['data'], $result['message']);
        }

        return $this->error($result['message'], 422);
    }

    /**
     * Process multiple scans (for offline sync)
     */
    public function syncOffline(Request $request): JsonResponse
    {
        $data = $request->validate([
            'scans' => ['required', 'array', 'min:1'],
            'scans.*.unique_code' => ['required', 'string'],
            'scans.*.waktu' => ['required', 'in:masuk,pulang'],
            'scans.*.scanned_at' => ['required', 'date'],
            'scans.*.latitude' => ['nullable', 'numeric'],
            'scans.*.longitude' => ['nullable', 'numeric'],
        ]);

        $results = [];
        $success = 0;
        $failed = 0;

        foreach ($data['scans'] as $scan) {
            $scanType = ScanType::from($scan['waktu']);

            $location = null;
            if (isset($scan['latitude']) && isset($scan['longitude'])) {
                $location = [
                    'latitude' => $scan['latitude'],
                    'longitude' => $scan['longitude'],
                ];
            }

            $result = $this->scanService->processScan(
                $scan['unique_code'],
                $scanType,
                $location
            );

            $results[] = [
                'unique_code' => $scan['unique_code'],
                'scanned_at' => $scan['scanned_at'],
                'success' => $result['success'],
                'message' => $result['message'],
            ];

            if ($result['success']) {
                $success++;
            } else {
                $failed++;
            }
        }

        return $this->success([
            'total' => count($data['scans']),
            'success' => $success,
            'failed' => $failed,
            'results' => $results,
        ], "Sync completed: {$success} berhasil, {$failed} gagal");
    }

    /**
     * Look up a code (preview who it belongs to)
     */
    public function lookup(Request $request): JsonResponse
    {
        $data = $request->validate([
            'unique_code' => ['required', 'string', 'max:100'],
        ]);

        $student = \App\Infrastructure\Persistence\Eloquent\Student\Student::findByCode($data['unique_code']);
        if ($student) {
            $student->load(['user', 'enrollments.classroom']);
            $enrollment = $student->currentEnrollment();

            return $this->success([
                'type' => 'student',
                'id' => $student->id,
                'nis' => $student->nis,
                'name' => $student->user?->full_name,
                'classroom' => $enrollment?->classroom?->name,
                'status' => $student->status,
            ], 'Siswa ditemukan');
        }

        $teacher = \App\Infrastructure\Persistence\Eloquent\Teacher\Teacher::findByCode($data['unique_code']);
        if ($teacher) {
            $teacher->load('user');

            return $this->success([
                'type' => 'teacher',
                'id' => $teacher->id,
                'nip' => $teacher->nip,
                'name' => $teacher->user?->full_name,
                'status' => $teacher->status,
            ], 'Guru ditemukan');
        }

        return $this->error('Kode tidak ditemukan', 404);
    }
}
