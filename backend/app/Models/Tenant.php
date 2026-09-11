<?php

namespace App\Models;

use App\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class Tenant extends Model
{
    use HasFactory, HasUuid, SoftDeletes;

    public const LOGIN_BRANDING_CACHE_KEY = 'login_branding';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'slug',
        'domain',
        'logo',
        'favicon',
        'npsn',
        'level',
        'status',
        'is_login_brand',
        'address',
        'phone',
        'email',
        'settings',
        'features',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'settings' => 'array',
        'features' => 'array',
        'is_login_brand' => 'boolean',
    ];

    /**
     * Get users belonging to this tenant.
     */
    public function users()
    {
        return $this->hasMany(User::class);
    }

    /**
     * Get the login branding data (cached).
     *
     * @return array{name: string, logo_url: string|null}|null
     */
    public static function getLoginBranding(): ?array
    {
        return Cache::remember(self::LOGIN_BRANDING_CACHE_KEY, 3600, function () {
            $tenant = self::where('is_login_brand', true)->first();

            if (! $tenant) {
                return null;
            }

            return [
                'name' => $tenant->name,
                'logo_url' => $tenant->logo
                    ? parse_url(Storage::disk('public')->url($tenant->logo), PHP_URL_PATH)
                    : null,
            ];
        });
    }

    /**
     * Set this tenant as the login brand (clears flag from all others).
     */
    public function setAsLoginBrand(): void
    {
        DB::transaction(function () {
            self::where('is_login_brand', true)
                ->lockForUpdate()
                ->update(['is_login_brand' => false]);

            $this->update(['is_login_brand' => true]);
        });

        Cache::forget(self::LOGIN_BRANDING_CACHE_KEY);
    }

    /**
     * Clear login brand from all tenants.
     */
    public static function clearLoginBrand(): void
    {
        self::where('is_login_brand', true)->update(['is_login_brand' => false]);
        Cache::forget(self::LOGIN_BRANDING_CACHE_KEY);
    }
}
