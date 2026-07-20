<?php

namespace App\Infrastructure\Persistence\Eloquent\Attendance;

use App\Infrastructure\Persistence\Eloquent\Concerns\BelongsToTenant;
use App\Infrastructure\Persistence\Eloquent\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Template kartu ID kustom per tenant (Fase 5 ATTENDANCE-PLAN.md §4a —
 * "Kartu ID Fase C"). Posisi/ukuran tiap elemen disimpan sebagai persen
 * dari dimensi kartu CR80 (85.6mm × 54mm) supaya konsisten antara editor
 * dan hasil cetak pada skala tampilan berapa pun.
 */
class CardTemplate extends Model
{
    use HasFactory, HasUuid, BelongsToTenant;

    protected $table = 'card_templates';

    protected $fillable = [
        'tenant_id',
        'type',
        'layout_json',
    ];

    protected function casts(): array
    {
        return [
            'layout_json' => 'array',
        ];
    }

    /**
     * Layout default — mencerminkan persis CSS fixed yang sudah dipakai
     * attendanceCardPrint.ts sejak Fase 2a, supaya tenant yang belum pernah
     * membuka editor tidak melihat perubahan visual apa pun.
     */
    public static function defaultLayout(): array
    {
        return [
            'cardBackground' => '#ffffff',
            'headerBackground' => '#1e3a5f',
            'elements' => [
                'logo' => ['x' => 4, 'y' => 8, 'width' => 12, 'height' => 20],
                'schoolName' => ['x' => 20, 'y' => 8, 'width' => 76, 'height' => 20, 'fontSize' => 3.4],
                'photo' => ['x' => 6, 'y' => 34, 'width' => 21, 'height' => 33],
                'qr' => ['x' => 32, 'y' => 30, 'width' => 30, 'height' => 30],
                'name' => ['x' => 66, 'y' => 32, 'width' => 30, 'height' => 10, 'fontSize' => 4.2],
                'idNumber' => ['x' => 66, 'y' => 46, 'width' => 30, 'height' => 8, 'fontSize' => 3.6],
                'subLine' => ['x' => 66, 'y' => 56, 'width' => 30, 'height' => 8, 'fontSize' => 3.2],
            ],
        ];
    }

    /**
     * Layout aktif tenant untuk satu jenis kartu — kustom bila sudah pernah
     * disimpan, default bila belum.
     */
    public static function activeLayoutFor(string $tenantId, string $type): array
    {
        $template = static::where('tenant_id', $tenantId)->where('type', $type)->first();

        return [
            'layout' => $template->layout_json ?? static::defaultLayout(),
            'is_custom' => $template !== null,
        ];
    }
}
