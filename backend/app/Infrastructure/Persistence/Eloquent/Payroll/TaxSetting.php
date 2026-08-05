<?php

namespace App\Infrastructure\Persistence\Eloquent\Payroll;

use App\Infrastructure\Persistence\Eloquent\Concerns\BelongsToTenant;
use App\Infrastructure\Persistence\Eloquent\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TaxSetting extends Model
{
    use HasFactory, HasUuid, BelongsToTenant;

    protected $table = 'tax_settings';

    protected $fillable = [
        'tenant_id',
        'setting_key',
        'setting_name',
        'setting_value',
        'category',
        'description',
        'effective_year',
        'effective_from',
        'effective_until',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'setting_value' => 'decimal:2',
            'effective_year' => 'integer',
            'effective_from' => 'date',
            'effective_until' => 'date',
            'is_active' => 'boolean',
        ];
    }

    // Constants for category
    public const CATEGORY_PTKP = 'ptkp';
    public const CATEGORY_BIAYA_JABATAN = 'biaya_jabatan';
    public const CATEGORY_TER = 'ter';
    public const CATEGORY_OTHER = 'other';

    public static function getCategories(): array
    {
        return [
            self::CATEGORY_PTKP => 'PTKP (Penghasilan Tidak Kena Pajak)',
            self::CATEGORY_BIAYA_JABATAN => 'Biaya Jabatan',
            self::CATEGORY_TER => 'TER (Tarif Efektif Rata-rata)',
            self::CATEGORY_OTHER => 'Lainnya',
        ];
    }

    // Common PTKP keys
    public const PTKP_TK0 = 'ptkp_tk0';  // Tidak Kawin / 0 tanggungan
    public const PTKP_TK1 = 'ptkp_tk1';  // Tidak Kawin / 1 tanggungan
    public const PTKP_TK2 = 'ptkp_tk2';  // Tidak Kawin / 2 tanggungan
    public const PTKP_TK3 = 'ptkp_tk3';  // Tidak Kawin / 3 tanggungan
    public const PTKP_K0 = 'ptkp_k0';    // Kawin / 0 tanggungan
    public const PTKP_K1 = 'ptkp_k1';    // Kawin / 1 tanggungan
    public const PTKP_K2 = 'ptkp_k2';    // Kawin / 2 tanggungan
    public const PTKP_K3 = 'ptkp_k3';    // Kawin / 3 tanggungan

    public static function getPtkpLabels(): array
    {
        return [
            self::PTKP_TK0 => 'TK/0 - Tidak Kawin, 0 Tanggungan',
            self::PTKP_TK1 => 'TK/1 - Tidak Kawin, 1 Tanggungan',
            self::PTKP_TK2 => 'TK/2 - Tidak Kawin, 2 Tanggungan',
            self::PTKP_TK3 => 'TK/3 - Tidak Kawin, 3 Tanggungan',
            self::PTKP_K0 => 'K/0 - Kawin, 0 Tanggungan',
            self::PTKP_K1 => 'K/1 - Kawin, 1 Tanggungan',
            self::PTKP_K2 => 'K/2 - Kawin, 2 Tanggungan',
            self::PTKP_K3 => 'K/3 - Kawin, 3 Tanggungan',
        ];
    }

    // Scopes

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeForYear(Builder $query, int $year): Builder
    {
        return $query->where('effective_year', $year);
    }

    public function scopeByCategory(Builder $query, string $category): Builder
    {
        return $query->where('category', $category);
    }

    public function scopeByKey(Builder $query, string $key): Builder
    {
        return $query->where('setting_key', $key);
    }

    public function scopePtkp(Builder $query): Builder
    {
        return $query->where('category', self::CATEGORY_PTKP);
    }

    // Helpers

    public function getCategoryLabel(): string
    {
        return self::getCategories()[$this->category] ?? $this->category;
    }

    /**
     * Get PTKP value for a given status and year.
     *
     * @param string $tenantId
     * @param string $status e.g., 'TK/0', 'K/1'
     * @param int $year
     * @return float
     */
    public static function getPtkpValue(string $tenantId, string $status, int $year): float
    {
        // Convert status format (TK/0 -> ptkp_tk0)
        $key = 'ptkp_' . strtolower(str_replace('/', '', $status));

        $setting = self::where('tenant_id', $tenantId)
            ->byKey($key)
            ->forYear($year)
            ->active()
            ->first();

        return $setting ? (float) $setting->setting_value : 0;
    }

    /**
     * Get biaya jabatan settings.
     */
    public static function getBiayaJabatan(string $tenantId, int $year): array
    {
        $settings = self::where('tenant_id', $tenantId)
            ->byCategory(self::CATEGORY_BIAYA_JABATAN)
            ->forYear($year)
            ->active()
            ->get()
            ->keyBy('setting_key');

        return [
            'rate' => (float) ($settings['biaya_jabatan_rate']->setting_value ?? 5), // Default 5%
            'max_per_year' => (float) ($settings['biaya_jabatan_max_year']->setting_value ?? 6000000), // Default 6 juta
            'max_per_month' => (float) ($settings['biaya_jabatan_max_month']->setting_value ?? 500000), // Default 500rb
        ];
    }
}
