<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
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
            'username' => $this->username,
            'email' => $this->email,
            'first_name' => $this->first_name,
            'last_name' => $this->last_name,
            'full_name' => trim($this->first_name . ' ' . $this->last_name),
            'phone' => $this->phone,
            'avatar' => $this->avatar,
            'avatar_url' => $this->avatar ? asset('storage/' . $this->avatar) : null,
            'is_active' => $this->isActive(),
            'status' => $this->status,
            'user_type' => $this->user_type,
            'email_verified_at' => $this->email_verified_at?->toISOString(),
            'last_login_at' => $this->last_login_at?->toISOString(),
            'last_login_ip' => $this->last_login_ip,
            'roles' => $this->whenLoaded('roles', fn() => $this->roles->pluck('name')),
            'teacher' => $this->whenLoaded('teacher', fn() => $this->teacher ? [
                'id' => $this->teacher->id,
                'nip' => $this->teacher->nip,
                'join_date' => $this->teacher->join_date?->toDateString(),
                'employment_status' => $this->teacher->employment_status,
                'education_level' => $this->teacher->education_level,
                'status' => $this->teacher->status,
            ] : null),
            'staff' => $this->whenLoaded('staff', fn() => $this->staff ? [
                'employee_id' => $this->staff->employee_id,
                'join_date' => $this->staff->join_date?->toDateString(),
                'employment_status' => $this->staff->employment_status,
            ] : null),
            'guardian_students' => $this->whenLoaded('guardianStudents', fn() => $this->guardianStudents->map(fn($g) => [
                'student_id' => $g->student_id,
                'relationship' => $g->relationship,
                'is_primary_contact' => (bool) $g->is_primary_contact,
                'nis' => $g->student?->nis,
                'name' => $g->student?->user?->full_name,
            ])->values()),
            'permissions' => $this->when(
                $request->routeIs('auth.me'),
                fn() => $this->getAllPermissions()->pluck('name')
            ),
            'tenant_id' => $this->tenant_id,
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
