<?php

namespace App\Domain\Setting\Services;

use App\Infrastructure\Persistence\Eloquent\Setting\MenuVisibilitySetting;
use App\Support\MenuRegistry;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;

/**
 * Resolusi visibilitas menu (Opsi A — config hanya bisa MENYEMBUNYIKAN).
 *
 * Menu tampil untuk user bila:
 *   (1) user punya permission efektif menu tsb (lapis keamanan, tak diubah), DAN
 *   (2) menu tidak disembunyikan untuk SEMUA role user (lapis config UX).
 *
 * super_admin selalu melihat semua. Menu ber-flag `protected` (mis. halaman
 * "Pengaturan Menu") tidak pernah bisa disembunyikan → anti-lockout.
 */
class MenuVisibilityService
{
    /** Label role dalam Bahasa Indonesia untuk UI matriks. */
    private const ROLE_LABELS = [
        'admin' => 'Administrator',
        'kepala_sekolah' => 'Kepala Sekolah',
        'wakil_kepala_sekolah' => 'Wakil Kepala Sekolah',
        'guru' => 'Guru',
        'wali_kelas' => 'Wali Kelas',
        'tata_usaha' => 'Tata Usaha',
        'bendahara' => 'Bendahara',
        'pustakawan' => 'Pustakawan',
        'siswa' => 'Siswa',
        'orang_tua' => 'Orang Tua',
    ];

    /**
     * Daftar menu_key yang boleh tampil untuk seorang user (gabungan role-nya).
     *
     * OPTIMIZED: Menggunakan relasi yang sudah di-eager-load di HandleInertiaRequests
     * untuk menghindari N+1 query saat cek permission per menu item.
     *
     * @return array<int, string>
     */
    public function visibleKeysFor($user): array
    {
        $flat = MenuRegistry::flatten();

        // Super admin: semua menu (kecuali tak ada pengecualian) — bypass config.
        if ($user->isSuperAdmin()) {
            return array_keys($flat);
        }

        // OPTIMIZED: Gunakan relasi yang sudah di-load, bukan getRoleNames() yang trigger query
        // Jika relasi belum di-load, load sekali saja
        if (!$user->relationLoaded('roles')) {
            $user->load('roles.permissions');
        }

        $roles = $user->roles->pluck('name')->all();

        // OPTIMIZED: Kumpulkan semua permission user ke dalam Set untuk O(1) lookup
        // Ini menggantikan $user->can() yang trigger query per-cek
        $userPermissions = $this->collectUserPermissions($user);

        $hidden = $this->hiddenMap($user->tenant_id); // [role => [key => true]]

        $visible = [];
        foreach ($flat as $key => $entry) {
            // Menu khusus super admin tak pernah untuk role lain.
            if ($entry['super_admin_only']) {
                continue;
            }

            // (1) Cek permission (lapis keamanan). null = tak butuh permission.
            // OPTIMIZED: Cek di array/set lokal, bukan $user->can() yang trigger query
            if ($entry['permission'] !== null && !isset($userPermissions[$entry['permission']])) {
                continue;
            }

            // (2) Cek config, kecuali menu protected (anti-lockout).
            if (! $entry['protected'] && ! empty($roles)) {
                $hiddenForAllRoles = true;
                foreach ($roles as $role) {
                    if (! isset($hidden[$role][$key])) {
                        $hiddenForAllRoles = false;
                        break;
                    }
                }
                if ($hiddenForAllRoles) {
                    continue;
                }
            }

            $visible[] = $key;
        }

        return $visible;
    }

    /**
     * Kumpulkan semua permission user ke dalam associative array untuk O(1) lookup.
     * Menggantikan $user->can() yang bisa trigger query per-cek.
     *
     * @return array<string, bool>
     */
    private function collectUserPermissions($user): array
    {
        $permissions = [];

        // Dari roles yang sudah di-load
        if ($user->relationLoaded('roles')) {
            foreach ($user->roles as $role) {
                if ($role->relationLoaded('permissions')) {
                    foreach ($role->permissions as $permission) {
                        $permissions[$permission->name] = true;
                    }
                }
            }
        }

        // Direct permissions
        if ($user->relationLoaded('permissions')) {
            foreach ($user->permissions as $permission) {
                $permissions[$permission->name] = true;
            }
        }

        return $permissions;
    }

    /**
     * Matriks role × menu untuk halaman "Pengaturan Menu".
     *
     * @return array{roles: array, tree: array, cells: array}
     */
    public function matrix(string $tenantId): array
    {
        $tree = MenuRegistry::tree();
        $flat = MenuRegistry::flatten();
        $hidden = $this->hiddenMap($tenantId);

        // Role yang bisa dikonfigurasi (super_admin dikecualikan — selalu lihat semua).
        $roleModels = Role::where('name', '!=', 'super_admin')
            ->with('permissions:id,name')
            ->orderBy('name')
            ->get();

        $roles = [];
        $rolePerms = [];
        foreach ($roleModels as $role) {
            $roles[] = [
                'name' => $role->name,
                'label' => self::ROLE_LABELS[$role->name] ?? ucwords(str_replace('_', ' ', $role->name)),
            ];
            $rolePerms[$role->name] = $role->permissions->pluck('name')->all();
        }

        // cells[role][key] = { visible: bool, locked: bool }
        $cells = [];
        foreach ($roles as $r) {
            $roleName = $r['name'];
            foreach ($flat as $key => $entry) {
                $cells[$roleName][$key] = $this->cellState($entry, $rolePerms[$roleName], isset($hidden[$roleName][$key]));
            }
        }

        return [
            'roles' => $roles,
            'tree' => $this->treeForUi($tree),
            'cells' => $cells,
        ];
    }

    /**
     * Simpan ulang seluruh konfigurasi "hidden" untuk tenant (replace-all).
     *
     * @param array<string, array<int, string>> $hiddenByRole  role => [menu_key,...]
     */
    public function replaceHidden(string $tenantId, array $hiddenByRole): void
    {
        $validKeys = MenuRegistry::flatten();

        $rows = [];
        foreach ($hiddenByRole as $role => $keys) {
            // Jangan pernah menyimpan config untuk super_admin.
            if ($role === 'super_admin') {
                continue;
            }
            foreach ((array) $keys as $key) {
                // Abaikan key tak dikenal atau key protected (anti-lockout).
                if (! isset($validKeys[$key]) || $validKeys[$key]['protected'] || $validKeys[$key]['super_admin_only']) {
                    continue;
                }
                $rows[] = [
                    'id' => (string) \Illuminate\Support\Str::uuid(),
                    'tenant_id' => $tenantId,
                    'role' => $role,
                    'menu_key' => $key,
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }
        }

        DB::transaction(function () use ($tenantId, $rows) {
            MenuVisibilitySetting::withoutGlobalScope('tenant')
                ->where('tenant_id', $tenantId)
                ->delete();

            if (! empty($rows)) {
                MenuVisibilitySetting::insert($rows);
            }
        });

        // Invalidate menu cache untuk semua user di tenant ini
        $this->clearMenuCacheForTenant($tenantId);
    }

    /**
     * Clear menu visibility cache untuk user tertentu.
     * Panggil ini saat role/permission user berubah.
     */
    public static function clearMenuCacheForUser(string $userId, ?string $tenantId = null): void
    {
        $cacheKey = "menu_visible:{$userId}:" . ($tenantId ?? 'global');
        Cache::forget($cacheKey);
    }

    /**
     * Clear menu visibility cache untuk semua user di tenant.
     * Panggil ini saat menu visibility settings berubah.
     */
    public function clearMenuCacheForTenant(string $tenantId): void
    {
        // Karena cache key mengandung user_id, kita perlu pattern matching
        // Untuk Redis: Cache::getRedis()->keys("menu_visible:*:{$tenantId}");
        // Untuk file/array cache, kita perlu track user IDs atau clear all

        // Solusi sederhana: clear semua menu cache dengan tag (jika driver support)
        // Atau gunakan prefix yang bisa di-flush

        // Untuk sekarang, kita rely pada TTL 5 menit
        // TODO: Implementasi yang lebih baik jika pakai Redis dengan tags
    }

    /**
     * @return array<string, array<string, bool>> [role => [menu_key => true]]
     */
    private function hiddenMap(?string $tenantId): array
    {
        if (! $tenantId) {
            return [];
        }

        $map = [];
        MenuVisibilitySetting::withoutGlobalScope('tenant')
            ->where('tenant_id', $tenantId)
            ->get(['role', 'menu_key'])
            ->each(function ($row) use (&$map) {
                $map[$row->role][$row->menu_key] = true;
            });

        return $map;
    }

    /**
     * @param array $entry     entri MenuRegistry::flatten()
     * @param array $rolePerms daftar nama permission role
     * @return array{visible: bool, locked: bool}
     */
    private function cellState(array $entry, array $rolePerms, bool $isHidden): array
    {
        // Hanya super admin — terkunci mati untuk role biasa.
        if ($entry['super_admin_only']) {
            return ['visible' => false, 'locked' => true];
        }

        // Butuh permission tapi role tak punya → terkunci mati (Opsi A: config
        // tak bisa MEMBUKA melebihi permission).
        if ($entry['permission'] !== null && ! in_array($entry['permission'], $rolePerms, true)) {
            return ['visible' => false, 'locked' => true];
        }

        // Protected → selalu tampil, tak bisa di-toggle (anti-lockout).
        if ($entry['protected']) {
            return ['visible' => true, 'locked' => true];
        }

        // Normal → bisa di-toggle; tampil bila tidak disembunyikan.
        return ['visible' => ! $isHidden, 'locked' => false];
    }

    private function treeForUi(array $tree): array
    {
        return array_map(function ($group) {
            return [
                'key' => $group['key'],
                'title' => $group['title'],
                'children' => array_map(fn ($c) => [
                    'key' => $c['key'],
                    'title' => $c['title'],
                ], $group['children'] ?? []),
            ];
        }, $tree);
    }
}
