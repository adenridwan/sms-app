<?php

namespace App\Infrastructure\Persistence\Eloquent\Report;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReportCardCharacter extends Model
{
    protected $fillable = [
        'report_card_id',
        'character_name',
        'predicate',
        'description',
    ];

    public const CHARACTERS = [
        'honesty' => 'Kejujuran',
        'discipline' => 'Kedisiplinan',
        'responsibility' => 'Tanggung Jawab',
        'tolerance' => 'Toleransi',
        'cooperation' => 'Gotong Royong',
        'politeness' => 'Sopan Santun',
        'confidence' => 'Percaya Diri',
    ];

    public const PREDICATES = [
        'excellent' => 'Sangat Baik',
        'good' => 'Baik',
        'fair' => 'Cukup',
        'poor' => 'Kurang',
    ];

    // ─────────────────────────────────────────────────────────────
    // Relationships
    // ─────────────────────────────────────────────────────────────

    public function reportCard(): BelongsTo
    {
        return $this->belongsTo(ReportCard::class);
    }
}
