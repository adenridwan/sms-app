<?php

namespace App\Infrastructure\Persistence\Eloquent\Teacher;

use App\Infrastructure\Persistence\Eloquent\Auth\User;
use App\Infrastructure\Persistence\Eloquent\Concerns\HasUuid;
use App\Infrastructure\Persistence\Eloquent\Concerns\BelongsToTenant;
use App\Infrastructure\Persistence\Eloquent\Attendance\LeavePermission;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

class Teacher extends Model implements HasMedia
{
    use HasFactory, HasUuid, BelongsToTenant, SoftDeletes, InteractsWithMedia;

    /**
     * Koleksi dokumen pemberkasan guru (Fase G2, TEACHER-MODULE-PLAN.md §2.6).
     * Semua opsional. KTP & NPWP hanya satu file (unggahan baru menimpa);
     * sisanya boleh lebih dari satu file.
     *
     * @var array<string, bool> nama koleksi => apakah singleFile
     */
    public const DOCUMENT_COLLECTIONS = [
        'ijazah' => false,
        'ktp' => true,
        'npwp' => true,
        'sertifikat_pendidik' => false,
        'surat_penugasan' => false,
        'lainnya' => false,
    ];

    public const DOCUMENT_MIME_TYPES = ['application/pdf', 'image/jpeg', 'image/png'];

    public const DOCUMENT_MAX_KB = 5120;

    public function registerMediaCollections(): void
    {
        foreach (self::DOCUMENT_COLLECTIONS as $name => $singleFile) {
            $collection = $this->addMediaCollection($name)
                ->acceptsMimeTypes(self::DOCUMENT_MIME_TYPES);

            if ($singleFile) {
                $collection->singleFile();
            }
        }
    }

    protected $table = 'teachers';

    protected $fillable = [
        'tenant_id',
        'user_id',
        'nip',
        'nuptk',
        'unique_code',
        'rfid_code',
        'no_hp',
        'join_date',
        'employment_status',
        'certification_status',
        'certification_number',
        'education_level',
        'education_major',
        'university',
        'teaching_experience_years',
        'status',
        'additional_info',
    ];

    protected function casts(): array
    {
        return [
            'join_date' => 'date',
            'teaching_experience_years' => 'integer',
            'additional_info' => 'array',
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
                $model->unique_code = 'TCH-' . strtoupper(Str::random(12));
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
     * Get employee attendance records
     */
    public function attendances(): HasMany
    {
        return $this->hasMany(
            \App\Infrastructure\Persistence\Eloquent\Attendance\EmployeeAttendance::class,
            'user_id',
            'user_id'
        );
    }

    /**
     * Get leave permissions
     */
    public function leavePermissions(): HasMany
    {
        return $this->hasMany(LeavePermission::class, 'teacher_id');
    }

    /**
     * Get subjects taught
     */
    public function subjects(): HasMany
    {
        return $this->hasMany(TeacherSubject::class, 'teacher_id');
    }

    /**
     * Ringkasan dokumen per koleksi untuk ditampilkan di form/detail.
     * Panggil setelah eager-load relasi 'media' agar tidak N+1.
     *
     * @return array<string, array<int, array{id:int,name:string,url:string,mime_type:?string,size:int,created_at:?string}>>
     */
    public function documentsSummary(): array
    {
        return collect(array_keys(self::DOCUMENT_COLLECTIONS))
            ->mapWithKeys(fn (string $collection) => [
                $collection => $this->getMedia($collection)->map(fn (Media $media) => [
                    'id' => $media->id,
                    'name' => $media->name,
                    'url' => $media->getUrl(),
                    'mime_type' => $media->mime_type,
                    'size' => $media->size,
                    'created_at' => $media->created_at?->toISOString(),
                ])->values()->all(),
            ])
            ->all();
    }

    /**
     * Generate new unique code
     */
    public function regenerateUniqueCode(): string
    {
        $this->unique_code = 'TCH-' . strtoupper(Str::random(12));
        $this->save();

        return $this->unique_code;
    }

    /**
     * Scope for active teachers
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
     * Find teacher by unique code or RFID code
     */
    public static function findByCode(string $code): ?static
    {
        return static::where('unique_code', $code)
            ->orWhere('rfid_code', $code)
            ->first();
    }
}
