<?php

namespace App\Http\Controllers\Api\V1\Student;

use App\Http\Controllers\Api\ApiController;
use App\Http\Resources\StudentAchievementResource;
use App\Infrastructure\Persistence\Eloquent\Student\Student;
use App\Infrastructure\Persistence\Eloquent\Student\StudentAchievement;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class AchievementController extends ApiController
{
    /**
     * List all achievements (optionally filtered).
     */
    public function index(Request $request): JsonResponse
    {
        abort_unless($request->user()->can('students.view'), 403);

        $query = StudentAchievement::query()
            ->with(['student.user.profile'])
            ->when($request->student_id, fn($q, $id) => $q->where('student_id', $id))
            ->when($request->category, fn($q, $cat) => $q->where('category', $cat))
            ->when($request->level, fn($q, $lvl) => $q->where('level', $lvl))
            ->when($request->search, function ($q, $search) {
                $q->where(function ($query) use ($search) {
                    $query->where('title', 'ilike', "%{$search}%")
                        ->orWhere('organizer', 'ilike', "%{$search}%")
                        ->orWhereHas('student', function ($sq) use ($search) {
                            $sq->where('nis', 'ilike', "%{$search}%")
                                ->orWhereHas('user.profile', function ($pq) use ($search) {
                                    $pq->where('first_name', 'ilike', "%{$search}%")
                                        ->orWhere('last_name', 'ilike', "%{$search}%");
                                });
                        });
                });
            })
            ->when($request->year, function ($q, $year) {
                $q->whereYear('achievement_date', $year);
            })
            ->orderBy('achievement_date', 'desc');

        $perPage = $request->get('per_page', 15);
        $achievements = $query->paginate($perPage);

        return $this->collection(StudentAchievementResource::collection($achievements));
    }

    /**
     * Get achievements for a specific student.
     */
    public function forStudent(Request $request, Student $student): JsonResponse
    {
        $this->authorize('view', $student);

        $achievements = $student->achievements()
            ->orderBy('achievement_date', 'desc')
            ->get();

        return $this->success(StudentAchievementResource::collection($achievements));
    }

    /**
     * Get a single achievement.
     */
    public function show(Request $request, StudentAchievement $achievement): JsonResponse
    {
        abort_unless($request->user()->can('students.view'), 403);

        $achievement->load(['student.user.profile']);

        return $this->success(new StudentAchievementResource($achievement));
    }

    /**
     * Create a new achievement.
     */
    public function store(Request $request): JsonResponse
    {
        abort_unless($request->user()->can('students.update'), 403);

        $data = $request->validate([
            'student_id' => ['required', 'uuid', 'exists:students,id'],
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:1000'],
            'category' => ['required', Rule::in(['academic', 'sports', 'arts', 'science', 'other'])],
            'level' => ['required', Rule::in(['school', 'district', 'city', 'province', 'national', 'international'])],
            'rank' => ['nullable', 'string', 'max:50'],
            'achievement_date' => ['required', 'date'],
            'organizer' => ['nullable', 'string', 'max:255'],
            'certificate' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:5120'],
        ]);

        try {
            DB::beginTransaction();

            // Handle certificate upload
            $certificatePath = null;
            if ($request->hasFile('certificate')) {
                $certificatePath = $request->file('certificate')->store('achievements/certificates', 'public');
            }

            $achievement = StudentAchievement::create([
                'tenant_id' => $this->currentTenantId($request),
                'student_id' => $data['student_id'],
                'title' => $data['title'],
                'description' => $data['description'] ?? null,
                'category' => $data['category'],
                'level' => $data['level'],
                'rank' => $data['rank'] ?? null,
                'achievement_date' => $data['achievement_date'],
                'organizer' => $data['organizer'] ?? null,
                'certificate_path' => $certificatePath,
            ]);

            DB::commit();

            $achievement->load(['student.user.profile']);

            return $this->success(
                new StudentAchievementResource($achievement),
                'Prestasi berhasil ditambahkan',
                201
            );
        } catch (\Exception $e) {
            DB::rollBack();

            // Clean up uploaded file if exists
            if (isset($certificatePath)) {
                Storage::disk('public')->delete($certificatePath);
            }

            return $this->error('Gagal menambahkan prestasi: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Update an achievement.
     */
    public function update(Request $request, StudentAchievement $achievement): JsonResponse
    {
        abort_unless($request->user()->can('students.update'), 403);

        $data = $request->validate([
            'title' => ['sometimes', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:1000'],
            'category' => ['sometimes', Rule::in(['academic', 'sports', 'arts', 'science', 'other'])],
            'level' => ['sometimes', Rule::in(['school', 'district', 'city', 'province', 'national', 'international'])],
            'rank' => ['nullable', 'string', 'max:50'],
            'achievement_date' => ['sometimes', 'date'],
            'organizer' => ['nullable', 'string', 'max:255'],
            'certificate' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:5120'],
        ]);

        try {
            DB::beginTransaction();

            // Handle certificate upload
            if ($request->hasFile('certificate')) {
                // Delete old certificate
                if ($achievement->certificate_path) {
                    Storage::disk('public')->delete($achievement->certificate_path);
                }
                $data['certificate_path'] = $request->file('certificate')->store('achievements/certificates', 'public');
            }

            // Remove certificate key as it's not a column
            unset($data['certificate']);

            $achievement->update($data);

            DB::commit();

            $achievement->load(['student.user.profile']);

            return $this->success(
                new StudentAchievementResource($achievement),
                'Prestasi berhasil diperbarui'
            );
        } catch (\Exception $e) {
            DB::rollBack();

            return $this->error('Gagal memperbarui prestasi: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Delete an achievement.
     */
    public function destroy(Request $request, StudentAchievement $achievement): JsonResponse
    {
        abort_unless($request->user()->can('students.update'), 403);

        try {
            // Delete certificate file if exists
            if ($achievement->certificate_path) {
                Storage::disk('public')->delete($achievement->certificate_path);
            }

            $achievement->delete();

            return $this->success(null, 'Prestasi berhasil dihapus');
        } catch (\Exception $e) {
            return $this->error('Gagal menghapus prestasi: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Delete certificate file.
     */
    public function deleteCertificate(Request $request, StudentAchievement $achievement): JsonResponse
    {
        abort_unless($request->user()->can('students.update'), 403);

        if ($achievement->certificate_path) {
            Storage::disk('public')->delete($achievement->certificate_path);
            $achievement->update(['certificate_path' => null]);
        }

        return $this->success(null, 'Sertifikat berhasil dihapus');
    }

    /**
     * Get achievement statistics.
     */
    public function statistics(Request $request): JsonResponse
    {
        abort_unless($request->user()->can('students.view'), 403);

        $query = StudentAchievement::query();

        if ($request->student_id) {
            $query->where('student_id', $request->student_id);
        }

        if ($request->year) {
            $query->whereYear('achievement_date', $request->year);
        }

        $total = (clone $query)->count();

        $byCategory = (clone $query)
            ->selectRaw('category, count(*) as count')
            ->groupBy('category')
            ->pluck('count', 'category');

        $byLevel = (clone $query)
            ->selectRaw('level, count(*) as count')
            ->groupBy('level')
            ->pluck('count', 'level');

        return $this->success([
            'total' => $total,
            'by_category' => $byCategory,
            'by_level' => $byLevel,
        ]);
    }
}
