<?php

namespace App\Infrastructure\Persistence\Eloquent\Attendance;

use App\Infrastructure\Persistence\Eloquent\Concerns\HasUuid;
use App\Infrastructure\Persistence\Eloquent\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Holiday extends Model
{
    use HasFactory, HasUuid, BelongsToTenant;

    protected $table = 'holidays';

    protected $fillable = [
        'tenant_id',
        'tanggal',
        'keterangan',
        'is_recurring',
    ];

    protected function casts(): array
    {
        return [
            'tanggal' => 'date',
            'is_recurring' => 'boolean',
        ];
    }

    /**
     * Check if a given date is a holiday
     */
    public static function isHoliday(string $date): bool
    {
        return static::where('tanggal', $date)->exists();
    }

    /**
     * Get holidays for a specific month
     */
    public function scopeForMonth(Builder $query, int $month, int $year): Builder
    {
        return $query->whereMonth('tanggal', $month)
            ->whereYear('tanggal', $year);
    }

    /**
     * Get holidays between dates
     */
    public function scopeBetweenDates(Builder $query, string $startDate, string $endDate): Builder
    {
        return $query->whereBetween('tanggal', [$startDate, $endDate]);
    }

    /**
     * Generate weekend holidays for a month
     */
    public static function generateWeekends(int $month, int $year, string $tenantId): int
    {
        $count = 0;
        $startDate = \Carbon\Carbon::create($year, $month, 1);
        $endDate = $startDate->copy()->endOfMonth();

        while ($startDate <= $endDate) {
            if ($startDate->isWeekend()) {
                $dayName = $startDate->isSaturday() ? 'Sabtu' : 'Minggu';
                static::firstOrCreate(
                    [
                        'tenant_id' => $tenantId,
                        'tanggal' => $startDate->toDateString(),
                    ],
                    [
                        'keterangan' => "Hari {$dayName}",
                        'is_recurring' => false,
                    ]
                );
                $count++;
            }
            $startDate->addDay();
        }

        return $count;
    }
}
