<?php

namespace App\Infrastructure\Persistence\Eloquent\System;

use App\Infrastructure\Persistence\Eloquent\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Model;

/**
 * Wrapper generik untuk tabel `settings` (group/key/value) yang sudah ada di
 * skema sejak awal tapi belum pernah dipakai model manapun. Dipakai pertama
 * kali untuk `security.db_config_access_password` (lihat DatabaseConnectionController).
 *
 * `tenant_id = null` berarti setting global (bukan punya satu sekolah/tenant),
 * karena itu model ini SENGAJA tidak pakai trait BelongsToTenant.
 */
class Setting extends Model
{
    use HasUuid;

    protected $table = 'settings';

    protected $fillable = [
        'tenant_id',
        'group',
        'key',
        'value',
        'type',
        'description',
        'is_public',
    ];

    protected function casts(): array
    {
        return [
            'is_public' => 'boolean',
        ];
    }

    public static function getGlobal(string $group, string $key): ?string
    {
        return static::whereNull('tenant_id')
            ->where('group', $group)
            ->where('key', $key)
            ->value('value');
    }

    public static function setGlobal(string $group, string $key, ?string $value, string $description = ''): void
    {
        static::updateOrCreate(
            ['tenant_id' => null, 'group' => $group, 'key' => $key],
            ['value' => $value, 'type' => 'string', 'description' => $description, 'is_public' => false],
        );
    }
}
