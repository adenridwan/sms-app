<?php

namespace App\Infrastructure\Persistence\Eloquent\Report;

use App\Infrastructure\Persistence\Eloquent\Academic\AcademicYear;
use App\Infrastructure\Persistence\Eloquent\Academic\Classroom;
use App\Infrastructure\Persistence\Eloquent\Academic\Semester;
use App\Infrastructure\Persistence\Eloquent\Exam\FinalGrade;
use App\Infrastructure\Persistence\Eloquent\Student\Student;
use App\Infrastructure\Persistence\Eloquent\Traits\BelongsToTenant;
use App\Infrastructure\Persistence\Eloquent\Traits\HasUuid;
use App\Infrastructure\Persistence\Eloquent\User\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ReportCard extends Model
{
    use HasUuid, BelongsToTenant;

    protected $fillable = [
        'tenant_id',
        'student_id',
        'classroom_id',
        'academic_year_id',
        'semester_id',
        'report_number',
        'total_subjects',
        'average_score',
        'rank_in_class',
        'total_students_in_class',
        'total_present_days',
        'total_absent_days',
        'total_sick_days',
        'total_permitted_days',
        'homeroom_notes',
        'principal_notes',
        'promotion_status',
        'next_classroom',
        'homeroom_teacher_id',
        'principal_id',
        'issued_date',
        'status',
    ];

    protected $casts = [
        'total_subjects' => 'integer',
        'average_score' => 'decimal:2',
        'rank_in_class' => 'integer',
        'total_students_in_class' => 'integer',
        'total_present_days' => 'integer',
        'total_absent_days' => 'integer',
        'total_sick_days' => 'integer',
        'total_permitted_days' => 'integer',
        'issued_date' => 'date',
    ];

    protected $appends = ['status_label', 'promotion_status_label'];

    public const STATUS_DRAFT = 'draft';
    public const STATUS_REVIEWED = 'reviewed';
    public const STATUS_APPROVED = 'approved';
    public const STATUS_PUBLISHED = 'published';
    public const STATUS_DISTRIBUTED = 'distributed';

    public const STATUSES = [
        self::STATUS_DRAFT => 'Draf',
        self::STATUS_REVIEWED => 'Direview',
        self::STATUS_APPROVED => 'Disetujui',
        self::STATUS_PUBLISHED => 'Dipublikasikan',
        self::STATUS_DISTRIBUTED => 'Didistribusikan',
    ];

    public const PROMOTION_PENDING = 'pending';
    public const PROMOTION_PROMOTED = 'promoted';
    public const PROMOTION_RETAINED = 'retained';
    public const PROMOTION_CONDITIONAL = 'conditional';

    public const PROMOTION_STATUSES = [
        self::PROMOTION_PENDING => 'Menunggu',
        self::PROMOTION_PROMOTED => 'Naik Kelas',
        self::PROMOTION_RETAINED => 'Tinggal Kelas',
        self::PROMOTION_CONDITIONAL => 'Naik Bersyarat',
    ];

    // ─────────────────────────────────────────────────────────────
    // Relationships
    // ─────────────────────────────────────────────────────────────

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function classroom(): BelongsTo
    {
        return $this->belongsTo(Classroom::class);
    }

    public function academicYear(): BelongsTo
    {
        return $this->belongsTo(AcademicYear::class);
    }

    public function semester(): BelongsTo
    {
        return $this->belongsTo(Semester::class);
    }

    public function homeroomTeacher(): BelongsTo
    {
        return $this->belongsTo(User::class, 'homeroom_teacher_id');
    }

    public function principal(): BelongsTo
    {
        return $this->belongsTo(User::class, 'principal_id');
    }

    public function extracurriculars(): HasMany
    {
        return $this->hasMany(ReportCardExtracurricular::class);
    }

    public function characters(): HasMany
    {
        return $this->hasMany(ReportCardCharacter::class);
    }

    public function grades(): HasMany
    {
        return $this->hasMany(FinalGrade::class, 'student_id', 'student_id')
            ->where('semester_id', $this->semester_id);
    }

    // ─────────────────────────────────────────────────────────────
    // Accessors
    // ─────────────────────────────────────────────────────────────

    public function getStatusLabelAttribute(): string
    {
        return self::STATUSES[$this->status] ?? $this->status;
    }

    public function getPromotionStatusLabelAttribute(): string
    {
        return self::PROMOTION_STATUSES[$this->promotion_status] ?? $this->promotion_status;
    }

    // ─────────────────────────────────────────────────────────────
    // Scopes
    // ─────────────────────────────────────────────────────────────

    public function scopeForStudent($query, string $studentId)
    {
        return $query->where('student_id', $studentId);
    }

    public function scopeForClassroom($query, string $classroomId)
    {
        return $query->where('classroom_id', $classroomId);
    }

    public function scopeForSemester($query, string $semesterId)
    {
        return $query->where('semester_id', $semesterId);
    }

    public function scopeForAcademicYear($query, string $academicYearId)
    {
        return $query->where('academic_year_id', $academicYearId);
    }

    public function scopeByStatus($query, string $status)
    {
        return $query->where('status', $status);
    }

    public function scopeSearch($query, ?string $search)
    {
        if (!$search) {
            return $query;
        }

        return $query->whereHas('student.user', function ($q) use ($search) {
            $q->where('full_name', 'ilike', "%{$search}%");
        })->orWhereHas('student', function ($q) use ($search) {
            $q->where('nis', 'ilike', "%{$search}%");
        });
    }

    // ─────────────────────────────────────────────────────────────
    // Methods
    // ─────────────────────────────────────────────────────────────

    public function canBeEdited(): bool
    {
        return in_array($this->status, [self::STATUS_DRAFT, self::STATUS_REVIEWED]);
    }

    public function canBeApproved(): bool
    {
        return $this->status === self::STATUS_REVIEWED;
    }

    public function canBePublished(): bool
    {
        return $this->status === self::STATUS_APPROVED;
    }

    public function approve(User $approver): void
    {
        $this->status = self::STATUS_APPROVED;
        $this->principal_id = $approver->id;
        $this->save();
    }

    public function publish(): void
    {
        $this->status = self::STATUS_PUBLISHED;
        $this->issued_date = now();
        $this->save();
    }

    public function markAsDistributed(): void
    {
        $this->status = self::STATUS_DISTRIBUTED;
        $this->save();
    }

    /**
     * Calculate statistics from final grades
     */
    public function calculateStatistics(): void
    {
        $grades = FinalGrade::where('student_id', $this->student_id)
            ->where('semester_id', $this->semester_id)
            ->get();

        $this->total_subjects = $grades->count();
        $this->average_score = $grades->count() > 0 ? round($grades->avg('final_score'), 2) : null;
        $this->save();
    }

    /**
     * Calculate rank in class
     */
    public function calculateRank(): void
    {
        // Get all report cards for the same classroom and semester
        $allReportCards = self::where('classroom_id', $this->classroom_id)
            ->where('semester_id', $this->semester_id)
            ->whereNotNull('average_score')
            ->orderByDesc('average_score')
            ->get();

        $this->total_students_in_class = $allReportCards->count();

        // Find this student's rank
        $rank = 1;
        foreach ($allReportCards as $rc) {
            if ($rc->id === $this->id) {
                $this->rank_in_class = $rank;
                break;
            }
            $rank++;
        }

        $this->save();
    }
}
