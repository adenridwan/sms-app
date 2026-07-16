<?php

namespace App\Models;

use App\Infrastructure\Persistence\Eloquent\Auth\UserProfile;
use Illuminate\Database\Eloquent\Relations\HasOne;

class User extends \App\Infrastructure\Persistence\Eloquent\Auth\User
{
    /**
     * Allow legacy controllers to pass profile fields during create/update.
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
        'first_name',
        'last_name',
        'phone',
        'is_active',
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
