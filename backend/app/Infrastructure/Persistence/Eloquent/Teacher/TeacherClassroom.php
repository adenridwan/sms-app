<?php

namespace App\Infrastructure\Persistence\Eloquent\Teacher;

use App\Infrastructure\Persistence\Eloquent\Academic\AcademicYear;
use App\Infrastructure\Persistence\Eloquent\Academic\Classroom;
use App\Infrastructure\Persistence\Eloquent\Auth\User;
use App\Infrastructure\Persistence\Eloquent\Concerns\BelongsToTenant;
use App\Infrastructure\Persistence\Eloquent\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Penugasan manual guru <-> kelas (sumber ketiga rumus "kelas diampu", R3).
 *
 * teacher_id merujuk users.id mengikuti konvensi schedules & classrooms.
 */
class TeacherClassroom extends Model
{
    use HasUuid, BelongsToTenant;

    protected $table = 'teacher_classrooms';

    protected $fillable = [
        'tenant_id',
        'teacher_id',
        'classroom_id',
        'academic_year_id',
    ];

    public function teacherUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'teacher_id');
    }

    public function classroom(): BelongsTo
    {
        return $this->belongsTo(Classroom::class, 'classroom_id');
    }

    public function academicYear(): BelongsTo
    {
        return $this->belongsTo(AcademicYear::class, 'academic_year_id');
    }
}
