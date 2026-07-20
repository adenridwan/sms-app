<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\ApiController;
use App\Services\DashboardStatsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Statistik dashboard per role (R5, ROLE-ACCESS-PLAN.md).
 *
 * Paket ditentukan role tertinggi user (bukan roles->first()) dan seluruh
 * datanya mengikuti scope R3/R4 lewat DashboardStatsService — service yang
 * sama dipakai PageController@dashboard.
 */
class DashboardController extends ApiController
{
    public function __construct(
        private DashboardStatsService $stats,
    ) {}

    /**
     * Get dashboard statistics.
     */
    public function index(Request $request): JsonResponse
    {
        return $this->success(
            $this->stats->statsFor($request->user()),
            'Dashboard statistics retrieved successfully'
        );
    }

    /**
     * Alias for index — the /dashboard/stats route.
     */
    public function stats(Request $request): JsonResponse
    {
        return $this->index($request);
    }

    /**
     * Statistik satu kelas untuk dropdown kelas diampu (Fase 3).
     *
     * Role administratif bebas; guru/wali_kelas hanya kelas diampu (R3).
     */
    public function classStats(Request $request, string $classroom): JsonResponse
    {
        $user = $request->user();
        $roles = $user->getRoleNames();

        $allowed = $roles->intersect(\App\Infrastructure\Persistence\Eloquent\Student\Student::ALL_ACCESS_ROLES)->isNotEmpty()
            || ($roles->intersect(['guru', 'wali_kelas'])->isNotEmpty()
                && in_array($classroom, $user->teachingClassroomIds(), true));

        if (! $allowed) {
            return $this->forbidden('Anda tidak memiliki akses ke kelas ini.');
        }

        return $this->success($this->stats->classStats($user, $classroom));
    }
}
