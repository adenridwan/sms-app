<?php

namespace App\Http\Resources\Academic;

use App\Infrastructure\Persistence\Eloquent\Academic\Schedule;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ScheduleResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'academic_year_id' => $this->academic_year_id,
            'semester_id' => $this->semester_id,
            'classroom_id' => $this->classroom_id,
            'subject_id' => $this->subject_id,
            'subject' => $this->whenLoaded('subject', fn () => [
                'id' => $this->subject->id,
                'name' => $this->subject->name,
                'code' => $this->subject->code,
            ]),
            'teacher_id' => $this->teacher_id,
            'teacher' => $this->whenLoaded('teacher', fn () => [
                'id' => $this->teacher->id,
                'full_name' => $this->teacher->full_name,
            ]),
            'time_slot_id' => $this->time_slot_id,
            'time_slot' => $this->whenLoaded('timeSlot', fn () => [
                'id' => $this->timeSlot->id,
                'name' => $this->timeSlot->name,
                'start_time' => substr((string) $this->timeSlot->start_time, 0, 5),
                'end_time' => substr((string) $this->timeSlot->end_time, 0, 5),
                'order' => $this->timeSlot->order,
                'is_break' => $this->timeSlot->is_break,
            ]),
            'day_of_week' => $this->day_of_week,
            'day_name' => Schedule::DAY_NAMES[(int) $this->day_of_week] ?? '',
            'room' => $this->room,
            'is_active' => $this->is_active,
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
