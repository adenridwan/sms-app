<?php

namespace App\Infrastructure\Persistence\Eloquent\Student;

use App\Infrastructure\Persistence\Eloquent\Auth\User;
use App\Infrastructure\Persistence\Eloquent\Concerns\HasUuid;
use App\Infrastructure\Persistence\Eloquent\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class StudentGuardian extends Model
{
    use HasFactory, HasUuid, BelongsToTenant, SoftDeletes;

    protected $table = 'student_guardians';

    protected $fillable = [
        'tenant_id',
        'student_id',
        'user_id',
        'relationship',
        'name',
        'nik',
        'phone',
        'email',
        'occupation',
        'income_range',
        'address',
        'education_level',
        'is_primary_contact',
        'is_emergency_contact',
    ];

    protected function casts(): array
    {
        return [
            'is_primary_contact' => 'boolean',
            'is_emergency_contact' => 'boolean',
        ];
    }

    /**
     * Get the student
     */
    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class, 'student_id');
    }

    /**
     * Get the user account if exists
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
