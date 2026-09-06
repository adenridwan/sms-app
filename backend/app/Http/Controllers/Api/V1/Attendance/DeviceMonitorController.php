<?php

namespace App\Http\Controllers\Api\V1\Attendance;

use App\Http\Controllers\Api\ApiController;
use App\Infrastructure\Persistence\Eloquent\Attendance\ScannerDevice;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/**
 * Device monitoring untuk admin: melihat status semua device scanner,
 * mendaftarkan device baru, dan mengelola device yang terdaftar.
 */
class DeviceMonitorController extends ApiController
{
    /**
     * Info server untuk koneksi device.
     *
     * GET /api/v1/devices/server-info
     */
    public function serverInfo(Request $request): JsonResponse
    {
        $localIp = $this->getLocalIp();
        $port = parse_url(config('app.url'), PHP_URL_PORT) ?? 8000;
        $baseUrl = config('app.url');

        // Jika app.url masih localhost, gunakan IP lokal
        if (str_contains($baseUrl, 'localhost') || str_contains($baseUrl, '127.0.0.1')) {
            $baseUrl = "http://{$localIp}:{$port}";
        }

        return $this->success([
            'status' => 'running',
            'local_ip' => $localIp,
            'port' => $port,
            'base_url' => $baseUrl,
            'api_url' => rtrim($baseUrl, '/') . '/api/v1',
            'public_url' => $this->getPublicUrl(),
            'server_time' => now()->toIso8601String(),
        ], 'Server info');
    }

    /**
     * Ringkasan status device untuk dashboard.
     *
     * GET /api/v1/devices/summary
     */
    public function summary(Request $request): JsonResponse
    {
        $tenantId = $this->currentTenantId($request);

        $query = ScannerDevice::query();
        if ($tenantId) {
            $query->where('tenant_id', $tenantId);
        }

        $total = (clone $query)->count();
        $online = (clone $query)->online()->count();
        $idle = (clone $query)->idle()->count();
        $offline = (clone $query)->offline()->count();
        $pendingTotal = (clone $query)->sum('pending_sync_count');

        return $this->success([
            'total' => $total,
            'online' => $online,
            'idle' => $idle,
            'offline' => $offline,
            'pending_sync_total' => (int) $pendingTotal,
        ], 'Device summary');
    }

    /**
     * Daftar semua device dengan status.
     *
     * GET /api/v1/devices
     */
    public function index(Request $request): JsonResponse
    {
        $tenantId = $this->currentTenantId($request);

        $query = ScannerDevice::with(['user:id,username,full_name,email'])
            ->when($tenantId, fn ($q) => $q->where('tenant_id', $tenantId))
            ->when($request->filled('status'), function ($q) use ($request) {
                match ($request->input('status')) {
                    'online' => $q->online(),
                    'idle' => $q->idle(),
                    'offline' => $q->offline(),
                    default => $q,
                };
            })
            ->when($request->filled('has_pending'), fn ($q) => $q->hasPending())
            ->when($request->filled('search'), function ($q) use ($request) {
                $search = $request->input('search');
                $q->where(function ($sub) use ($search) {
                    $sub->where('device_name', 'like', "%{$search}%")
                        ->orWhere('location', 'like', "%{$search}%");
                });
            })
            ->orderByRaw('CASE
                WHEN last_heartbeat_at >= ? THEN 1
                WHEN last_heartbeat_at >= ? THEN 2
                ELSE 3
            END', [now()->subMinutes(2), now()->subMinutes(15)])
            ->orderByDesc('last_heartbeat_at');

        $perPage = min($request->integer('per_page', 20), 100);
        $devices = $query->paginate($perPage);

        return $this->success([
            'data' => $devices->map(fn ($d) => $this->formatDevice($d)),
            'meta' => [
                'current_page' => $devices->currentPage(),
                'last_page' => $devices->lastPage(),
                'per_page' => $devices->perPage(),
                'total' => $devices->total(),
            ],
        ], 'Device list');
    }

    /**
     * Detail satu device.
     *
     * GET /api/v1/devices/{device}
     */
    public function show(ScannerDevice $device): JsonResponse
    {
        $device->load(['user:id,username,full_name,email']);

        // Hitung statistik hari ini
        $todayStats = $this->getDeviceTodayStats($device);

        return $this->success([
            'device' => $this->formatDevice($device, detailed: true),
            'today_stats' => $todayStats,
        ], 'Device detail');
    }

    /**
     * Buat device baru (dari dashboard admin).
     *
     * POST /api/v1/devices
     */
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'device_name' => ['required', 'string', 'max:100'],
            'location' => ['nullable', 'string', 'max:255'],
            'user_id' => ['nullable', 'uuid', 'exists:users,id'],
        ]);

        $tenantId = $this->currentTenantId($request);
        if (!$tenantId) {
            return $this->error('Tenant ID diperlukan', 400);
        }

        $device = ScannerDevice::create([
            'tenant_id' => $tenantId,
            'device_name' => $data['device_name'],
            'location' => $data['location'] ?? null,
            'user_id' => $data['user_id'] ?? null,
            'device_token' => Str::random(64),
            'is_active' => true,
        ]);

        return $this->created(
            $this->formatDevice($device->fresh(['user'])),
            'Device berhasil didaftarkan'
        );
    }

    /**
     * Update info device.
     *
     * PUT /api/v1/devices/{device}
     */
    public function update(Request $request, ScannerDevice $device): JsonResponse
    {
        $data = $request->validate([
            'device_name' => ['sometimes', 'required', 'string', 'max:100'],
            'location' => ['nullable', 'string', 'max:255'],
            'user_id' => ['nullable', 'uuid', 'exists:users,id'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        $device->update($data);

        return $this->success(
            $this->formatDevice($device->fresh(['user'])),
            'Device berhasil diupdate'
        );
    }

    /**
     * Hapus device.
     *
     * DELETE /api/v1/devices/{device}
     */
    public function destroy(ScannerDevice $device): JsonResponse
    {
        $device->delete();

        return $this->deleted('Device berhasil dihapus');
    }

    /**
     * Regenerate token device (untuk keamanan).
     *
     * POST /api/v1/devices/{device}/regenerate-token
     */
    public function regenerateToken(ScannerDevice $device): JsonResponse
    {
        $newToken = Str::random(64);
        $device->update(['device_token' => $newToken]);

        return $this->success([
            'device_token' => $newToken,
        ], 'Token berhasil di-regenerate. Device perlu login ulang.');
    }

    /**
     * Heartbeat dari device mobile.
     *
     * POST /api/v1/devices/heartbeat
     *
     * Endpoint ini dipanggil berkala oleh app mobile untuk melaporkan status.
     */
    public function heartbeat(Request $request): JsonResponse
    {
        $data = $request->validate([
            'app_version' => ['nullable', 'string', 'max:20'],
            'os_version' => ['nullable', 'string', 'max:50'],
            'device_model' => ['nullable', 'string', 'max:100'],
            'battery_level' => ['nullable', 'integer', 'between:0,100'],
            'battery_charging' => ['nullable', 'boolean'],
            'network_type' => ['nullable', 'string', Rule::in(['wifi', 'mobile', 'ethernet', 'none'])],
            'network_name' => ['nullable', 'string', 'max:100'],
            'latency_ms' => ['nullable', 'integer', 'min:0'],
            'pending_sync_count' => ['nullable', 'integer', 'min:0'],
        ]);

        $user = $request->user();

        // Cari atau buat device untuk user ini
        $device = ScannerDevice::where('user_id', $user->id)->first();

        if (!$device) {
            // Auto-create device untuk user ini
            $device = ScannerDevice::create([
                'tenant_id' => $user->tenant_id,
                'user_id' => $user->id,
                'device_name' => $data['device_model'] ?? 'Device ' . $user->username,
                'device_token' => Str::random(64),
                'is_active' => true,
            ]);
        }

        $device->recordHeartbeat([
            'app_version' => $data['app_version'] ?? $device->app_version,
            'os_version' => $data['os_version'] ?? $device->os_version,
            'device_model' => $data['device_model'] ?? $device->device_model,
            'battery_level' => $data['battery_level'] ?? $device->battery_level,
            'battery_charging' => $data['battery_charging'] ?? $device->battery_charging,
            'network_type' => $data['network_type'] ?? $device->network_type,
            'network_name' => $data['network_name'] ?? $device->network_name,
            'latency_ms' => $data['latency_ms'] ?? $device->latency_ms,
            'pending_sync_count' => $data['pending_sync_count'] ?? $device->pending_sync_count,
        ]);

        return $this->success([
            'device_id' => $device->id,
            'server_time' => now()->toIso8601String(),
        ], 'Heartbeat recorded');
    }

    /**
     * Generate QR provisioning dengan server URL.
     *
     * POST /api/v1/devices/provision-qr
     */
    public function generateProvisionQr(Request $request): JsonResponse
    {
        $data = $request->validate([
            'user_id' => ['required', 'uuid', 'exists:users,id'],
            'device_name' => ['nullable', 'string', 'max:100'],
            'location' => ['nullable', 'string', 'max:255'],
        ]);

        $user = User::findOrFail($data['user_id']);

        if ($user->status !== 'active') {
            return $this->error('Tidak dapat membuat QR untuk akun yang tidak aktif.', 422);
        }

        // Buat provision token
        $provisionToken = \App\Infrastructure\Persistence\Eloquent\Auth\ProvisionToken::create([
            'user_id' => $user->id,
            'token' => Str::random(64),
            'expires_at' => now()->addMinutes(15),
            'created_by' => $request->user()->id,
        ]);

        // Buat QR content dengan server URL
        $serverUrl = $this->getApiUrl();
        $qrContent = "smsapp://provision?" . http_build_query([
            'token' => $provisionToken->token,
            'server' => $serverUrl,
            'device_name' => $data['device_name'] ?? null,
            'location' => $data['location'] ?? null,
        ]);

        return $this->success([
            'qr_content' => $qrContent,
            'provision_token' => $provisionToken->token,
            'server_url' => $serverUrl,
            'expires_at' => $provisionToken->expires_at->toIso8601String(),
            'expires_in_minutes' => 15,
            'user' => [
                'id' => $user->id,
                'name' => $user->full_name,
                'email' => $user->email,
            ],
            'device_info' => [
                'name' => $data['device_name'] ?? null,
                'location' => $data['location'] ?? null,
            ],
        ], 'QR provisioning berhasil dibuat');
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Private helpers
    // ─────────────────────────────────────────────────────────────────────────

    private function formatDevice(ScannerDevice $device, bool $detailed = false): array
    {
        $data = [
            'id' => $device->id,
            'device_name' => $device->device_name,
            'location' => $device->location,
            'is_active' => $device->is_active,
            'connection_status' => $device->connection_status,
            'last_seen' => $device->last_seen_text,
            'last_heartbeat_at' => $device->last_heartbeat_at?->toIso8601String(),
            'last_scan_at' => $device->last_scan_at?->toIso8601String(),
            'pending_sync_count' => $device->pending_sync_count,
            'user' => $device->user ? [
                'id' => $device->user->id,
                'name' => $device->user->full_name,
                'email' => $device->user->email,
            ] : null,
        ];

        if ($detailed) {
            $data = array_merge($data, [
                'app_version' => $device->app_version,
                'os_version' => $device->os_version,
                'device_model' => $device->device_model,
                'battery_level' => $device->battery_level,
                'battery_charging' => $device->battery_charging,
                'network_type' => $device->network_type,
                'network_name' => $device->network_name,
                'latency_ms' => $device->latency_ms,
                'created_at' => $device->created_at->toIso8601String(),
                'updated_at' => $device->updated_at->toIso8601String(),
            ]);
        }

        return $data;
    }

    private function getDeviceTodayStats(ScannerDevice $device): array
    {
        // Count dari offline_scan_queue untuk device ini hari ini
        $today = now()->toDateString();

        $stats = DB::table('offline_scan_queue')
            ->where('scanner_device_id', $device->id)
            ->whereDate('scanned_at', $today)
            ->selectRaw("
                COUNT(*) as total,
                SUM(CASE WHEN scan_type = 'masuk' THEN 1 ELSE 0 END) as check_in,
                SUM(CASE WHEN scan_type = 'pulang' THEN 1 ELSE 0 END) as check_out,
                SUM(CASE WHEN sync_status = 'synced' THEN 1 ELSE 0 END) as synced,
                SUM(CASE WHEN sync_status = 'failed' THEN 1 ELSE 0 END) as failed,
                SUM(CASE WHEN sync_status = 'pending' THEN 1 ELSE 0 END) as pending
            ")
            ->first();

        return [
            'total_scans' => (int) ($stats->total ?? 0),
            'check_in' => (int) ($stats->check_in ?? 0),
            'check_out' => (int) ($stats->check_out ?? 0),
            'synced' => (int) ($stats->synced ?? 0),
            'failed' => (int) ($stats->failed ?? 0),
            'pending' => (int) ($stats->pending ?? 0),
        ];
    }

    private function getLocalIp(): string
    {
        // Coba dapatkan IP lokal
        $localIp = gethostbyname(gethostname());

        // Fallback jika tidak bisa detect
        if ($localIp === gethostname() || $localIp === '127.0.0.1') {
            // Coba cara lain untuk Windows/Linux
            if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
                exec('ipconfig', $output);
                foreach ($output as $line) {
                    if (preg_match('/IPv4.*?:\s*([\d.]+)/', $line, $matches)) {
                        if ($matches[1] !== '127.0.0.1') {
                            return $matches[1];
                        }
                    }
                }
            } else {
                exec("hostname -I | awk '{print $1}'", $output);
                if (!empty($output[0])) {
                    return trim($output[0]);
                }
            }
        }

        return $localIp ?: '127.0.0.1';
    }

    private function getPublicUrl(): ?string
    {
        $url = config('app.url');

        // Jika URL bukan localhost, anggap itu public URL
        if (!str_contains($url, 'localhost') && !str_contains($url, '127.0.0.1')) {
            return $url;
        }

        return null;
    }

    private function getApiUrl(): string
    {
        $localIp = $this->getLocalIp();
        $port = parse_url(config('app.url'), PHP_URL_PORT) ?? 8000;
        $publicUrl = $this->getPublicUrl();

        if ($publicUrl) {
            return rtrim($publicUrl, '/') . '/api/v1';
        }

        return "http://{$localIp}:{$port}/api/v1";
    }
}
