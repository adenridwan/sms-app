<?php

namespace App\Infrastructure\Persistence\Eloquent\Student;

use App\Infrastructure\Persistence\Eloquent\Academic\Classroom;
use App\Infrastructure\Persistence\Eloquent\Auth\User;
use App\Infrastructure\Persistence\Eloquent\Concerns\HasUuid;
use App\Infrastructure\Persistence\Eloquent\Concerns\BelongsToTenant;
use App\Infrastructure\Persistence\Eloquent\Attendance\StudentAttendance;
use App\Infrastructure\Persistence\Eloquent\Attendance\LeavePermission;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOneThrough;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Student extends Model
{
    use HasFactory, HasUuid, BelongsToTenant, SoftDeletes;

    protected $table = 'students';

    protected $fillable = [
        'tenant_id',
        'user_id',
        'nis',
        'nisn',
        'unique_code',
        'rfid_code',
        'poin_pelanggaran',
        'entry_date',
        'entry_type',
        'previous_school',
        'status',
        'graduation_date',
        'graduation_certificate_number',
        'additional_info',
    ];

    protected function casts(): array
    {
        return [
            'entry_date' => 'date',
            'graduation_date' => 'date',
            'additional_info' => 'array',
            'poin_pelanggaran' => 'integer',
        ];
    }

    /**
     * Boot the model
     */
    protected static function boot(): void
    {
        parent::boot();

        static::creating(function ($model) {
            if (empty($model->unique_code)) {
                $model->unique_code = 'STU-' . strtoupper(Str::random(12));
            }
        });
    }

    /**
     * Get the user account
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * Get student guardians
     */
    public function guardians(): HasMany
    {
        return $this->hasMany(StudentGuardian::class, 'student_id');
    }

    /**
     * Alias untuk guardians — dipakai StudentResource ('parents')
     * dan StudentController@show yang eager-load 'parents.user'.
     */
    public function parents(): HasMany
    {
        return $this->hasMany(StudentGuardian::class, 'student_id');
    }

    /**
     * Get primary guardian
     */
    public function primaryGuardian()
    {
        return $this->guardians()->where('is_primary_contact', true)->first();
    }

    /**
     * Get attendance records
     */
    public function attendances(): HasMany
    {
        return $this->hasMany(StudentAttendance::class, 'student_id');
    }

    /**
     * Get leave permissions
     */
    public function leavePermissions(): HasMany
    {
        return $this->hasMany(LeavePermission::class, 'student_id');
    }

    /**
     * Get enrollments
     */
    public function enrollments(): HasMany
    {
        return $this->hasMany(StudentEnrollment::class, 'student_id');
    }

    /**
     * Get achievements
     */
    public function achievements(): HasMany
    {
        return $this->hasMany(StudentAchievement::class, 'student_id');
    }

    /**
     * Get current classroom via the active enrollment
     */
    public function currentClass(): HasOneThrough
    {
        return $this->hasOneThrough(
            Classroom::class,
            StudentEnrollment::class,
            'student_id',   // FK on student_enrollments referencing students
            'id',           // key on classrooms
            'id',           // local key on students
            'classroom_id'  // key on student_enrollments referencing classrooms
        )->where('student_enrollments.status', 'active');
    }

    /**
     * Get current enrollment
     */
    public function currentEnrollment()
    {
        return $this->enrollments()
            ->where('status', 'active')
            ->latest('enrollment_date')
            ->first();
    }

    /**
     * Get student's phone number from primary guardian
     */
    public function getGuardianPhoneAttribute(): ?string
    {
        $guardian = $this->primaryGuardian();
        return $guardian?->phone;
    }

    /**
     * Add violation points
     */
    public function addViolationPoints(int $points): void
    {
        $this->increment('poin_pelanggaran', $points);
    }

    /**
     * Reset violation points
     */
    public function resetViolationPoints(): void
    {
        $this->update(['poin_pelanggaran' => 0]);
    }

    /**
     * Generate new unique code
     */
    public function regenerateUniqueCode(): string
    {
        $this->unique_code = 'STU-' . strtoupper(Str::random(12));
        $this->save();

        return $this->unique_code;
    }

    /**
     * Role yang boleh melihat seluruh siswa dalam satu tenant (R4).
     */
    public const ALL_ACCESS_ROLES = [
        'super_admin',
        'admin',
        'kepala_sekolah',
        'wakil_kepala_sekolah',
        'tata_usaha',
        'bendahara',
        'pustakawan',
    ];

    /**
     * Scope data siswa sesuai role user (rumus R4, ROLE-ACCESS-PLAN.md).
     *
     * - Role administratif: semua siswa (tenant scope tetap berlaku).
     * - guru / wali_kelas: siswa dengan enrollment aktif di kelas diampu (R3).
     * - siswa: dirinya sendiri.
     * - orang_tua: anak-anaknya via student_guardians.user_id.
     * - Selain itu: tidak ada baris.
     */
    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        $roles = $user->getRoleNames();

        if ($roles->intersect(self::ALL_ACCESS_ROLES)->isNotEmpty()) {
            return $query;
        }

        if ($roles->intersect(['guru', 'wali_kelas'])->isNotEmpty()) {
            $classroomIds = $user->teachingClassroomIds();

            if ($classroomIds === []) {
                return $query->whereRaw('1 = 0');
            }

            return $query->whereHas('enrollments', fn (Builder $q) => $q
                ->where('status', 'active')
                ->whereIn('classroom_id', $classroomIds));
        }

        if ($roles->contains('siswa')) {
            return $query->where('user_id', $user->id);
        }

        if ($roles->contains('orang_tua')) {
            return $query->whereHas('guardians', fn (Builder $q) => $q
                ->where('user_id', $user->id));
        }

        return $query->whereRaw('1 = 0');
    }

    /**
     * Scope for active students
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', 'active');
    }

    /**
     * Scope to search by unique code or RFID
     */
    public function scopeFindByCode(Builder $query, string $code): Builder
    {
        return $query->where('unique_code', $code)
            ->orWhere('rfid_code', $code);
    }

    /**
     * Find student by unique code or RFID code
     */
    public static function findByCode(string $code): ?static
    {
        return static::where('unique_code', $code)
            ->orWhere('rfid_code', $code)
            ->first();
    }
}
