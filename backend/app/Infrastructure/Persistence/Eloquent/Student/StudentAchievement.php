<?php

namespace App\Infrastructure\Persistence\Eloquent\Student;

use App\Infrastructure\Persistence\Eloquent\Concerns\HasUuid;
use App\Infrastructure\Persistence\Eloquent\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class StudentAchievement extends Model
{
    use HasFactory, HasUuid, BelongsToTenant, SoftDeletes;

    protected $table = 'student_achievements';

    protected $fillable = [
        'tenant_id',
        'student_id',
        'title',
        'description',
        'category',
        'level',
        'rank',
        'achievement_date',
        'organizer',
        'certificate_path',
    ];

    protected function casts(): array
    {
        return [
            'achievement_date' => 'date',
        ];
    }

    /**
     * Get the student that owns the achievement.
     */
    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class, 'student_id');
    }

    /**
     * Scope for a specific category.
     */
    public function scopeCategory(Builder $query, string $category): Builder
    {
        return $query->where('category', $category);
    }

    /**
     * Scope for a specific level.
     */
    public function scopeLevel(Builder $query, string $level): Builder
    {
        return $query->where('level', $level);
    }

    /**
     * Get category label.
     */
    public function getCategoryLabelAttribute(): string
    {
        return match ($this->category) {
            'academic' => 'Akademik',
            'sports' => 'Olahraga',
            'arts' => 'Seni',
            'science' => 'Sains',
            'other' => 'Lainnya',
            default => ucfirst($this->category ?? ''),
        };
    }

    /**
     * Get level label.
     */
    public function getLevelLabelAttribute(): string
    {
        return match ($this->level) {
            'school' => 'Sekolah',
            'district' => 'Kecamatan',
            'city' => 'Kota/Kabupaten',
            'province' => 'Provinsi',
            'national' => 'Nasional',
            'international' => 'Internasional',
            default => ucfirst($this->level ?? ''),
        };
    }
}
