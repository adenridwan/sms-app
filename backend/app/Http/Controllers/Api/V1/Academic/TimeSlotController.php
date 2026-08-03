<?php

namespace App\Http\Controllers\Api\V1\Academic;

use App\Http\Controllers\Api\ApiController;
use App\Http\Resources\Academic\TimeSlotResource;
use App\Infrastructure\Persistence\Eloquent\Academic\TimeSlot;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TimeSlotController extends ApiController
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request): JsonResponse
    {
        $query = TimeSlot::query()->orderBy('order')->orderBy('start_time');

        $perPage = min((int) $request->get('per_page', 50), 100);
        $timeSlots = $query->paginate($perPage);

        return $this->success(TimeSlotResource::collection($timeSlots)->response()->getData(true));
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request): JsonResponse
    {
        abort_unless($request->user()->can('schedules.manage'), 403);

        if (! $this->currentTenantId($request)) {
            return $this->error('Konteks sekolah (tenant) tidak ditemukan. Pilih sekolah terlebih dahulu.', 422);
        }

        $data = $request->validate([
            'name' => ['required', 'string', 'max:50'],
            'start_time' => ['required', 'date_format:H:i'],
            'end_time' => ['required', 'date_format:H:i', 'after:start_time'],
            'order' => ['required', 'integer', 'min:0'],
            'is_break' => ['boolean'],
        ]);

        $timeSlot = TimeSlot::create($data);

        return $this->success(new TimeSlotResource($timeSlot), 'Jam pelajaran berhasil ditambahkan', 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(TimeSlot $timeSlot): JsonResponse
    {
        return $this->success(new TimeSlotResource($timeSlot));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, TimeSlot $timeSlot): JsonResponse
    {
        abort_unless($request->user()->can('schedules.manage'), 403);

        $data = $request->validate([
            'name' => ['sometimes', 'string', 'max:50'],
            'start_time' => ['sometimes', 'date_format:H:i'],
            'end_time' => ['sometimes', 'date_format:H:i', 'after:start_time'],
            'order' => ['sometimes', 'integer', 'min:0'],
            'is_break' => ['boolean'],
        ]);

        $timeSlot->update($data);

        return $this->success(new TimeSlotResource($timeSlot), 'Jam pelajaran berhasil diperbarui');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(TimeSlot $timeSlot): JsonResponse
    {
        abort_unless(request()->user()->can('schedules.manage'), 403);

        if ($timeSlot->schedules()->exists()) {
            return $this->error('Jam pelajaran tidak dapat dihapus karena masih dipakai jadwal', 422);
        }

        $timeSlot->delete();

        return $this->success(null, 'Jam pelajaran berhasil dihapus');
    }
}
