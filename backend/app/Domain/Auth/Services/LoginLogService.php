<?php

namespace App\Domain\Auth\Services;

use App\Infrastructure\Persistence\Eloquent\Auth\AuthLoginLog;
use App\Infrastructure\Persistence\Eloquent\Auth\User;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;

class LoginLogService
{
    public function record(
        Request $request,
        ?User $user,
        string $email,
        string $method,
        bool $successful,
        ?string $failureReason = null
    ): AuthLoginLog {
        return AuthLoginLog::create([
            'user_id' => $user?->id,
            'email' => $email,
            'ip_address' => $request->ip(),
            'user_agent' => substr((string) $request->userAgent(), 0, 255),
            'method' => $method,
            'successful' => $successful,
            'failure_reason' => $failureReason,
        ]);
    }

    /** @param array{user_id?:string,email?:string,successful?:bool,method?:string,from_date?:string,to_date?:string} $filters */
    public function paginate(array $filters = [], int $perPage = 25): LengthAwarePaginator
    {
        // `full_name` adalah accessor (dirakit dari user_profiles), bukan kolom
        // users — memasukkannya ke daftar select membuat query gagal dengan
        // "column full_name does not exist" begitu ada log yang punya user_id.
        // Profil diambil utuh karena User::$appends (first_name/last_name/
        // phone/full_name) semuanya membacanya saat diserialisasi.
        return AuthLoginLog::with(['user' => fn ($q) => $q
            ->select('id', 'username', 'email')
            ->with('profile')])
            ->when($filters['user_id'] ?? null, fn ($q, $v) => $q->where('user_id', $v))
            ->when($filters['email'] ?? null, fn ($q, $v) => $q->where('email', 'like', "%{$v}%"))
            ->when($filters['method'] ?? null, fn ($q, $v) => $q->where('method', $v))
            ->when(
                array_key_exists('successful', $filters) && $filters['successful'] !== null,
                fn ($q) => $q->where('successful', (bool) $filters['successful'])
            )
            ->when($filters['from_date'] ?? null, fn ($q, $v) => $q->whereDate('created_at', '>=', $v))
            ->when($filters['to_date'] ?? null, fn ($q, $v) => $q->whereDate('created_at', '<=', $v))
            ->latest('created_at')
            ->paginate($perPage)
            ->withQueryString();
    }
}
