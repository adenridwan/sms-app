<?php

namespace App\Infrastructure\Persistence\Eloquent\Report;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReportCardExtracurricular extends Model
{
    protected $fillable = [
        'report_card_id',
        'activity_name',
        'predicate',
        'description',
    ];

    // ─────────────────────────────────────────────────────────────
    // Relationships
    // ─────────────────────────────────────────────────────────────

    public function reportCard(): BelongsTo
    {
        return $this->belongsTo(ReportCard::class);
    }
}
