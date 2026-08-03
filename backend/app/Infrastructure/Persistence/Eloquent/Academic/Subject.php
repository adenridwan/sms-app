<?php

namespace App\Infrastructure\Persistence\Eloquent\Academic;

use App\Infrastructure\Persistence\Eloquent\Concerns\BelongsToTenant;
use App\Infrastructure\Persistence\Eloquent\Concerns\HasUuid;
use App\Infrastructure\Persistence\Eloquent\Teacher\TeacherSubject;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Subject extends Model
{
    use HasFactory, HasUuid, BelongsToTenant, SoftDeletes;

    /**
     * Kategori mata pelajaran yang valid, dipakai untuk validasi form.
     */
    public const CATEGORIES = [
        'Wajib',
        'Peminatan IPA',
        'Peminatan IPS',
        'Muatan Lokal',
    ];

    protected $table = 'subjects';

    protected $fillable = [
        'tenant_id',
        'curriculum_id',
        'name',
        'code',
        'category',
        'description',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function curriculum(): BelongsTo
    {
        return $this->belongsTo(Curriculum::class, 'curriculum_id');
    }

    public function teacherSubjects(): HasMany
    {
        return $this->hasMany(TeacherSubject::class, 'subject_id');
    }
}
