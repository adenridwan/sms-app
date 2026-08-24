<?php

namespace App\Domain\Attendance\Services;

use App\Infrastructure\Persistence\Eloquent\Attendance\AttendanceAuditLog;
use Illuminate\Pagination\LengthAwarePaginator;

class AuditLogService
{
    /**
     * Log an attendance action
     */
    public function log(
        string $aksi,
        string $tabel,
        ?string $recordId = null,
        ?array $dataLama = null,
        ?array $dataBaru = null,
        ?string $tenantId = null
    ): AttendanceAuditLog {
        return AttendanceAuditLog::log($aksi, $tabel, $recordId, $dataLama, $dataBaru, $tenantId);
    }

    /**
     * Get audit logs with filters
     */
    public function getLogs(array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        $query = AttendanceAuditLog::with('user')
            ->orderBy('created_at', 'desc');

        if (!empty($filters['aksi'])) {
            $query->where('aksi', $filters['aksi']);
        }

        if (!empty($filters['tabel'])) {
            $query->where('tabel', $filters['tabel']);
        }

        if (!empty($filters['user_id'])) {
            $query->where('user_id', $filters['user_id']);
        }

        if (!empty($filters['record_id'])) {
            $query->where('record_id', $filters['record_id']);
        }

        if (!empty($filters['from_date'])) {
            $query->whereDate('created_at', '>=', $filters['from_date']);
        }

        if (!empty($filters['to_date'])) {
            $query->whereDate('created_at', '<=', $filters['to_date']);
        }

        return $query->paginate($perPage);
    }

    /**
     * Get logs for a specific record
     */
    public function getLogsForRecord(string $tabel, string $recordId): \Illuminate\Support\Collection
    {
        return AttendanceAuditLog::with('user')
            ->where('tabel', $tabel)
            ->where('record_id', $recordId)
            ->orderBy('created_at', 'desc')
            ->get();
    }

    /**
     * Get recent scan logs
     */
    public function getRecentScans(int $limit = 50): \Illuminate\Support\Collection
    {
        return AttendanceAuditLog::with('user')
            ->whereIn('aksi', [
                'scan_masuk_siswa',
                'scan_pulang_siswa',
                'scan_masuk_guru',
                'scan_pulang_guru',
            ])
            ->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get();
    }

    /**
     * Get action statistics for a date range
     */
    public function getActionStats(string $startDate, string $endDate): array
    {
        return AttendanceAuditLog::whereBetween('created_at', [$startDate, $endDate])
            ->selectRaw('aksi, COUNT(*) as count')
            ->groupBy('aksi')
            ->pluck('count', 'aksi')
            ->toArray();
    }
}
