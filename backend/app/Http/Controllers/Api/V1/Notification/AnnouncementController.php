<?php

namespace App\Http\Controllers\Api\V1\Notification;

use App\Http\Controllers\Api\ApiController;
use App\Http\Resources\AnnouncementResource;
use App\Infrastructure\Persistence\Eloquent\Notification\Announcement;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class AnnouncementController extends ApiController
{
    /**
     * List announcements.
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $isSuperAdmin = $user->user_type === 'super_admin';
        $canManage = $isSuperAdmin || $user->can('announcements.manage');

        $query = Announcement::query()
            ->with(['author:id,username,email', 'author.profile'])
            ->when(!$canManage, function ($q) {
                // Non-admin users only see published announcements
                $q->published();
            })
            ->when($request->priority, fn($q, $priority) => $q->where('priority', $priority))
            ->when($request->is_published !== null, function ($q) use ($request) {
                $q->where('is_published', filter_var($request->is_published, FILTER_VALIDATE_BOOLEAN));
            })
            ->when($request->is_pinned !== null, function ($q) use ($request) {
                $q->where('is_pinned', filter_var($request->is_pinned, FILTER_VALIDATE_BOOLEAN));
            })
            ->when($request->search, function ($q, $search) {
                $q->where(function ($query) use ($search) {
                    $query->where('title', 'ilike', "%{$search}%")
                        ->orWhere('content', 'ilike', "%{$search}%");
                });
            })
            ->orderByDesc('is_pinned')
            ->orderByDesc('created_at');

        $perPage = $request->get('per_page', 15);
        $announcements = $query->paginate($perPage);

        // Add read status for current user
        $announcements->getCollection()->transform(function ($announcement) use ($user) {
            $announcement->is_read = $announcement->isReadBy($user);
            return $announcement;
        });

        return $this->collection(AnnouncementResource::collection($announcements));
    }

    /**
     * Get a single announcement.
     */
    public function show(Request $request, Announcement $announcement): JsonResponse
    {
        $user = $request->user();

        // Non-admin can only view published announcements
        if (!$this->canManage($user) && !$announcement->is_active) {
            abort(404);
        }

        $announcement->load(['author:id,username,email', 'author.profile']);

        // Mark as read
        $announcement->markAsReadBy($user);

        $announcement->is_read = true;

        return $this->success(new AnnouncementResource($announcement));
    }

    /**
     * Create a new announcement.
     */
    public function store(Request $request): JsonResponse
    {
        abort_unless($this->canManage($request->user()), 403);

        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'content' => ['required', 'string'],
            'priority' => ['nullable', Rule::in(['low', 'normal', 'high', 'urgent'])],
            'target_audience' => ['nullable', 'array'],
            'target_audience.roles' => ['nullable', 'array'],
            'target_audience.classrooms' => ['nullable', 'array'],
            'target_audience.grade_levels' => ['nullable', 'array'],
            'publish_at' => ['nullable', 'date'],
            'expires_at' => ['nullable', 'date', 'after:publish_at'],
            'is_pinned' => ['nullable', 'boolean'],
            'is_published' => ['nullable', 'boolean'],
            'send_notification' => ['nullable', 'boolean'],
            'image' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
        ]);

        try {
            DB::beginTransaction();

            // Handle image upload
            $imagePath = null;
            if ($request->hasFile('image')) {
                $imagePath = $request->file('image')->store('announcements', 'public');
            }

            $announcement = Announcement::create([
                'tenant_id' => $this->currentTenantId($request),
                'created_by' => $request->user()->id,
                'title' => $data['title'],
                'content' => $data['content'],
                'image' => $imagePath,
                'priority' => $data['priority'] ?? 'normal',
                'target_audience' => $data['target_audience'] ?? null,
                'publish_at' => $data['publish_at'] ?? null,
                'expires_at' => $data['expires_at'] ?? null,
                'is_pinned' => $data['is_pinned'] ?? false,
                'is_published' => $data['is_published'] ?? false,
                'send_notification' => $data['send_notification'] ?? true,
            ]);

            DB::commit();

            $announcement->load(['author:id,username,email', 'author.profile']);

            return $this->success(
                new AnnouncementResource($announcement),
                'Pengumuman berhasil dibuat',
                201
            );
        } catch (\Exception $e) {
            DB::rollBack();

            if (isset($imagePath)) {
                Storage::disk('public')->delete($imagePath);
            }

            return $this->error('Gagal membuat pengumuman: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Update an announcement.
     */
    public function update(Request $request, Announcement $announcement): JsonResponse
    {
        abort_unless($this->canManage($request->user()), 403);

        $data = $request->validate([
            'title' => ['sometimes', 'string', 'max:255'],
            'content' => ['sometimes', 'string'],
            'priority' => ['nullable', Rule::in(['low', 'normal', 'high', 'urgent'])],
            'target_audience' => ['nullable', 'array'],
            'publish_at' => ['nullable', 'date'],
            'expires_at' => ['nullable', 'date'],
            'is_pinned' => ['nullable', 'boolean'],
            'is_published' => ['nullable', 'boolean'],
            'send_notification' => ['nullable', 'boolean'],
            'image' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
        ]);

        try {
            DB::beginTransaction();

            // Handle image upload
            if ($request->hasFile('image')) {
                // Delete old image
                if ($announcement->image) {
                    Storage::disk('public')->delete($announcement->image);
                }
                $data['image'] = $request->file('image')->store('announcements', 'public');
            }

            $announcement->update($data);

            DB::commit();

            $announcement->load(['author:id,username,email', 'author.profile']);

            return $this->success(
                new AnnouncementResource($announcement),
                'Pengumuman berhasil diperbarui'
            );
        } catch (\Exception $e) {
            DB::rollBack();

            return $this->error('Gagal memperbarui pengumuman: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Delete an announcement.
     */
    public function destroy(Request $request, Announcement $announcement): JsonResponse
    {
        abort_unless($this->canManage($request->user()), 403);

        try {
            // Delete image if exists
            if ($announcement->image) {
                Storage::disk('public')->delete($announcement->image);
            }

            $announcement->delete();

            return $this->success(null, 'Pengumuman berhasil dihapus');
        } catch (\Exception $e) {
            return $this->error('Gagal menghapus pengumuman: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Publish/unpublish an announcement.
     */
    public function publish(Request $request, Announcement $announcement): JsonResponse
    {
        abort_unless($this->canManage($request->user()), 403);

        $request->validate([
            'is_published' => ['required', 'boolean'],
        ]);

        $announcement->update([
            'is_published' => $request->is_published,
            'publish_at' => $request->is_published ? ($announcement->publish_at ?? now()) : $announcement->publish_at,
        ]);

        $message = $request->is_published
            ? 'Pengumuman berhasil dipublikasikan'
            : 'Pengumuman berhasil di-unpublish';

        return $this->success(new AnnouncementResource($announcement), $message);
    }

    /**
     * Delete announcement image.
     */
    public function deleteImage(Request $request, Announcement $announcement): JsonResponse
    {
        abort_unless($this->canManage($request->user()), 403);

        if ($announcement->image) {
            Storage::disk('public')->delete($announcement->image);
            $announcement->update(['image' => null]);
        }

        return $this->success(null, 'Gambar berhasil dihapus');
    }

    /**
     * Mark announcement as read for current user.
     */
    public function markAsRead(Request $request, Announcement $announcement): JsonResponse
    {
        $announcement->markAsReadBy($request->user());

        return $this->success(null, 'Pengumuman ditandai sudah dibaca');
    }

    /**
     * Umpan untuk ikon lonceng di navbar: pengumuman yang benar-benar tayang
     * untuk pengguna ini, beserta jumlah yang belum dibaca.
     *
     * Sengaja terpisah dari index(): index() memakai `announcements.manage`
     * sehingga admin ikut melihat draft dan pengumuman kedaluwarsa — tidak
     * pantas muncul sebagai notifikasi. Di sini scope `published()` berlaku
     * untuk semua orang tanpa kecuali, termasuk super admin.
     */
    public function feed(Request $request): JsonResponse
    {
        $user = $request->user();

        $validated = $request->validate([
            'limit' => ['nullable', 'integer', 'min:1', 'max:20'],
        ]);

        $limit = (int) ($validated['limit'] ?? 5);

        $unreadCount = Announcement::published()
            ->whereDoesntHave('readers', fn ($q) => $q->where('users.id', $user->id))
            ->count();

        $items = Announcement::published()
            ->with(['author:id,username,email', 'author.profile'])
            ->withExists(['readers as is_read' => fn ($q) => $q->where('users.id', $user->id)])
            ->orderByDesc('is_pinned')
            ->orderByDesc('publish_at')
            ->orderByDesc('created_at')
            ->limit($limit)
            ->get()
            // withExists() mengembalikan 0/1, bukan boolean — samakan dengan
            // bentuk yang dipakai index() supaya frontend tidak perlu tahu
            // bedanya dari mana data itu datang.
            ->each(fn (Announcement $a) => $a->is_read = (bool) $a->is_read);

        return $this->success([
            'unread_count' => $unreadCount,
            'items' => AnnouncementResource::collection($items),
        ]);
    }

    /**
     * Get announcement statistics.
     */
    public function statistics(Request $request): JsonResponse
    {
        abort_unless($this->canManage($request->user()), 403);

        $total = Announcement::count();
        $published = Announcement::where('is_published', true)->count();
        $draft = Announcement::where('is_published', false)->count();
        $pinned = Announcement::where('is_pinned', true)->count();

        $byPriority = Announcement::selectRaw('priority, count(*) as count')
            ->groupBy('priority')
            ->pluck('count', 'priority');

        return $this->success([
            'total' => $total,
            'published' => $published,
            'draft' => $draft,
            'pinned' => $pinned,
            'by_priority' => $byPriority,
        ]);
    }

    /**
     * Check if user can manage announcements.
     */
    private function canManage($user): bool
    {
        return $user->user_type === 'super_admin' || $user->can('announcements.manage');
    }
}
