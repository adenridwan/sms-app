<?php

namespace App\Models;

use App\Infrastructure\Persistence\Eloquent\Auth\UserProfile;
use Illuminate\Database\Eloquent\Relations\HasOne;

class User extends \App\Infrastructure\Persistence\Eloquent\Auth\User
{
    /**
     * Spatie's guard-guessing (Guard::getNames()) matches instances against
     * config('auth.providers.*.model') by strict class equality — this
     * subclass never matches, so it falls back to config('auth.defaults.guard'),
     * which auth:sanctum middleware mutates to 'sanctum' for the rest of the
     * request (Authenticate::authenticate() calls Auth::shouldUse()). Every
     * role is seeded under guard 'web' only, so assignRole() on a fresh
     * instance of this class then throws "no role named ... for guard
     * sanctum". Declaring guard_name explicitly short-circuits the guessing.
     *
     * @var string
     */
    protected $guard_name = 'web';

    /**
     * Allow legacy controllers to pass profile fields during create/update.
     *
     * @var list<string>
     */
    protected $fillable = [
        'tenant_id',
        'username',
        'email',
        'contact_email',
        'contact_email_verified_at',
        'email_is_generated',
        'password',
        'avatar',
        'status',
        'user_type',
        'last_login_at',
        'last_login_ip',
        'preferences',
        'first_name',
        'last_name',
        'phone',
        'is_active',
        'gender',
        'birth_place',
        'birth_date',
        'religion',
        'address',
        'id_number',
    ];

    /**
     * Temporary profile payload that will be synchronized after save.
     *
     * @var array<string, mixed>
     */
    protected array $pendingProfileData = [];

    /**
     * Boot the model.
     */
    protected static function booted(): void
    {
        static::saved(function (self $user): void {
            if ($user->pendingProfileData === []) {
                return;
            }

            $user->profile()->updateOrCreate(
                ['user_id' => $user->id],
                $user->pendingProfileData
            );

            $user->pendingProfileData = [];
        });
    }

    /**
     * Capture first name for profile syncing.
     */
    public function setFirstNameAttribute($value): void
    {
        $this->pendingProfileData['first_name'] = $value;
    }

    /**
     * Capture last name for profile syncing.
     */
    public function setLastNameAttribute($value): void
    {
        $this->pendingProfileData['last_name'] = $value;
    }

    /**
     * Capture phone for profile syncing.
     */
    public function setPhoneAttribute($value): void
    {
        $this->pendingProfileData['phone'] = $value;
    }

    /**
     * Capture gender for profile syncing.
     */
    public function setGenderAttribute($value): void
    {
        $this->pendingProfileData['gender'] = $value;
    }

    /**
     * Capture birth place for profile syncing.
     */
    public function setBirthPlaceAttribute($value): void
    {
        $this->pendingProfileData['birth_place'] = $value;
    }

    /**
     * Capture birth date for profile syncing.
     */
    public function setBirthDateAttribute($value): void
    {
        $this->pendingProfileData['birth_date'] = $value;
    }

    /**
     * Capture religion for profile syncing.
     */
    public function setReligionAttribute($value): void
    {
        $this->pendingProfileData['religion'] = $value;
    }

    /**
     * Capture address for profile syncing.
     */
    public function setAddressAttribute($value): void
    {
        $this->pendingProfileData['address'] = $value;
    }

    /**
     * Capture ID number (NIK) for profile syncing.
     */
    public function setIdNumberAttribute($value): void
    {
        $this->pendingProfileData['id_number'] = $value;
    }

    /**
     * Map legacy boolean flag to the users.status column.
     */
    public function setIsActiveAttribute($value): void
    {
        $this->attributes['status'] = $value ? 'active' : 'inactive';
    }

    /**
     * Expose the active flag from the status column.
     */
    public function getIsActiveAttribute(): bool
    {
        return $this->status === 'active';
    }

    /**
     * Sync any pending profile data if the relation is touched manually.
     */
    public function profile(): HasOne
    {
        return parent::profile()->withDefault(function () {
            return new UserProfile();
        });
    }
}
