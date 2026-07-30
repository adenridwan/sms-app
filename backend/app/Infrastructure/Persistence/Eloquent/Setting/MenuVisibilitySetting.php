<?php

namespace App\Infrastructure\Persistence\Eloquent\Setting;

use App\Infrastructure\Persistence\Eloquent\Concerns\BelongsToTenant;
use App\Infrastructure\Persistence\Eloquent\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Model;

/**
 * Satu baris = (role, menu_key) yang DISEMBUNYIKAN untuk tenant ini.
 * Lihat migration 0001_01_01_000020 & MenuVisibilityService.
 */
class MenuVisibilitySetting extends Model
{
    use HasUuid, BelongsToTenant;

    protected $table = 'menu_visibility_settings';

    protected $fillable = [
        'tenant_id',
        'role',
        'menu_key',
    ];
}
