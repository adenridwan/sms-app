<?php

namespace App\Http\Controllers\Api\V1\Attendance;

use App\Http\Controllers\Api\ApiController;
use App\Infrastructure\Persistence\Eloquent\Attendance\Holiday;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class HolidayController extends ApiController
{
    /**
     * List holidays
     */
    public function index(Request $request): JsonResponse
    {
        $query = Holiday::query()
            ->when($request->month, function ($q, $month) use ($request) {
                $year = $request->year ?? now()->year;
                $q->forMonth($month, $year);
            })
            ->when($request->year && !$request->month, fn($q, $year) => $q->whereYear('tanggal', $year))
            ->when($request->from_date && $request->to_date, function ($q) use ($request) {
                $q->betweenDates($request->from_date, $request->to_date);
            });

        $holidays = $query->orderBy('tanggal', 'asc')->get();

        return $this->success($holidays);
    }

    /**
     * Store a new holiday
     */
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'tanggal' => ['required', 'date'],
            'keterangan' => ['required', 'string', 'max:255'],
            'is_recurring' => ['boolean'],
        ]);

        // Check if holiday already exists
        $existing = Holiday::where('tanggal', $data['tanggal'])->first();
        if ($existing) {
            return $this->error('Hari libur untuk tanggal ini sudah ada', 422);
        }

        $holiday = Holiday::create([
            'tenant_id' => $request->user()->tenant_id,
            'tanggal' => $data['tanggal'],
            'keterangan' => $data['keterangan'],
            'is_recurring' => $data['is_recurring'] ?? false,
        ]);

        return $this->created($holiday, 'Hari libur berhasil ditambahkan');
    }

    /**
     * Show a specific holiday
     */
    public function show(Holiday $holiday): JsonResponse
    {
        return $this->success($holiday);
    }

    /**
     * Update a holiday
     */
    public function update(Request $request, Holiday $holiday): JsonResponse
    {
        $data = $request->validate([
            'tanggal' => ['sometimes', 'date'],
            'keterangan' => ['sometimes', 'string', 'max:255'],
            'is_recurring' => ['boolean'],
        ]);

        // Check for duplicate if date is being changed
        if (isset($data['tanggal']) && $data['tanggal'] !== $holiday->tanggal->toDateString()) {
            $existing = Holiday::where('tanggal', $data['tanggal'])
                ->where('id', '!=', $holiday->id)
                ->first();
            if ($existing) {
                return $this->error('Hari libur untuk tanggal ini sudah ada', 422);
            }
        }

        $holiday->update($data);

        return $this->success($holiday, 'Hari libur berhasil diperbarui');
    }

    /**
     * Delete a holiday
     */
    public function destroy(Holiday $holiday): JsonResponse
    {
        $holiday->delete();

        return $this->deleted('Hari libur berhasil dihapus');
    }

    /**
     * Generate weekend holidays for a month
     */
    public function generateWeekends(Request $request): JsonResponse
    {
        $data = $request->validate([
            'month' => ['required', 'integer', 'between:1,12'],
            'year' => ['required', 'integer', 'min:2020', 'max:2100'],
        ]);

        $count = Holiday::generateWeekends(
            $data['month'],
            $data['year'],
            $request->user()->tenant_id
        );

        return $this->success(
            ['count' => $count],
            "{$count} hari libur weekend berhasil ditambahkan"
        );
    }

    /**
     * Bulk delete holidays
     */
    public function bulkDelete(Request $request): JsonResponse
    {
        $data = $request->validate([
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['uuid', 'exists:holidays,id'],
        ]);

        $deleted = Holiday::whereIn('id', $data['ids'])->delete();

        return $this->success(
            ['deleted' => $deleted],
            "{$deleted} hari libur berhasil dihapus"
        );
    }

    /**
     * Check if a date is a holiday
     */
    public function check(Request $request): JsonResponse
    {
        $data = $request->validate([
            'date' => ['required', 'date'],
        ]);

        $holiday = Holiday::where('tanggal', $data['date'])->first();

        return $this->success([
            'is_holiday' => $holiday !== null,
            'holiday' => $holiday,
        ]);
    }
}
