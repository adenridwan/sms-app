<?php

namespace App\Infrastructure\Persistence\Eloquent\Auth;

use App\Infrastructure\Persistence\Eloquent\Concerns\HasUuid;
use App\Infrastructure\Persistence\Eloquent\Concerns\BelongsToTenant;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable implements MustVerifyEmail
{
    use HasApiTokens;
    use HasFactory;
    use HasRoles;
    use HasUuid;
    use BelongsToTenant;
    use Notifiable;
    use SoftDeletes;

    /**
     * The table associated with the model.
     */
    protected $table = 'users';

    /**
     * The primary key type.
     */
    protected $keyType = 'string';

    /**
     * Indicates if the IDs are auto-incrementing.
     */
    public $incrementing = false;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'tenant_id',
        'username',
        'email',
        'password',
        'avatar',
        'status',
        'user_type',
        'last_login_at',
        'last_login_ip',
        'preferences',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Accessors exposed through the API resource.
     *
     * @var list<string>
     */
    protected $appends = [
        'first_name',
        'last_name',
        'phone',
        'full_name',
    ];

    /**
     * The attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'last_login_at' => 'datetime',
            'password' => 'hashed',
            'preferences' => 'array',
        ];
    }

    /**
     * Tenant scope source for the User model.
     *
     * Unlike other tenant-scoped models, user identity resolution (Sanctum
     * token / session auth) must never be filtered by the client-supplied
     * X-Tenant-ID header or the tenant container, otherwise super admin
     * accounts (tenant_id = null) can never be authenticated when that
     * header is present. Users are only scoped by the authenticated
     * user's own tenant.
     */
    protected static function getCurrentTenantId(): ?string
    {
        // Guard against infinite recursion: resolving the authenticated user
        // re-queries this very model, which re-triggers the tenant scope.
        static $resolvingAuthUser = false;

        if ($resolvingAuthUser) {
            return null;
        }

        $resolvingAuthUser = true;

        try {
            return auth()->user()?->tenant_id;
        } finally {
            $resolvingAuthUser = false;
        }
    }

    /**
     * Get the tenant that owns the user.
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(
            \App\Infrastructure\Persistence\Eloquent\Tenant\Tenant::class,
            'tenant_id'
        );
    }

    /**
     * Get the user's profile.
     */
    public function profile(): HasOne
    {
        return $this->hasOne(UserProfile::class, 'user_id');
    }

    /**
     * Get the first name attribute from the profile when present.
     */
    public function getFirstNameAttribute(): ?string
    {
        return $this->profile?->first_name;
    }

    /**
     * Get the last name attribute from the profile when present.
     */
    public function getLastNameAttribute(): ?string
    {
        return $this->profile?->last_name;
    }

    /**
     * Get the phone attribute from the profile when present.
     */
    public function getPhoneAttribute(): ?string
    {
        return $this->profile?->phone;
    }

    /**
     * Get the user's student record.
     */
    public function student(): HasOne
    {
        return $this->hasOne(
            \App\Infrastructure\Persistence\Eloquent\Student\Student::class,
            'user_id'
        );
    }

    /**
     * Get the user's teacher record.
     */
    public function teacher(): HasOne
    {
        return $this->hasOne(
            \App\Infrastructure\Persistence\Eloquent\Teacher\Teacher::class,
            'user_id'
        );
    }

    /**
     * Get the user's staff record.
     */
    public function staff(): HasOne
    {
        return $this->hasOne(
            \App\Infrastructure\Persistence\Eloquent\Staff\Staff::class,
            'user_id'
        );
    }

    /**
     * Check if user is super admin.
     */
    public function isSuperAdmin(): bool
    {
        return $this->user_type === 'super_admin';
    }

    /**
     * Check if user is admin.
     */
    public function isAdmin(): bool
    {
        return in_array($this->user_type, ['super_admin', 'admin']);
    }

    /**
     * Check if user is teacher.
     */
    public function isTeacher(): bool
    {
        return $this->user_type === 'teacher';
    }

    /**
     * Check if user is student.
     */
    public function isStudent(): bool
    {
        return $this->user_type === 'student';
    }

    /**
     * Check if user is parent.
     */
    public function isParent(): bool
    {
        return $this->user_type === 'parent';
    }

    /**
     * Check if user is active.
     */
    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    /**
     * Get user's full name from profile.
     */
    public function getFullNameAttribute(): string
    {
        return trim(($this->first_name ?? '') . ' ' . ($this->last_name ?? '')) ?: $this->username;
    }

    /**
     * Scope a query to only include active users.
     */
    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    /**
     * Scope a query to filter by user type.
     */
    public function scopeOfType($query, string $type)
    {
        return $query->where('user_type', $type);
    }
}
