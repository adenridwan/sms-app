<?php

namespace App\Http\Controllers\Api\V1\Student;

use App\Http\Controllers\Api\ApiController;
use App\Http\Resources\ParentResource;
use App\Models\Student\StudentParent;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class ParentController extends ApiController
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request): JsonResponse
    {
        $query = StudentParent::with(['user', 'students.user'])
            ->when($request->search, function ($q, $search) {
                $q->whereHas('user', function ($q) use ($search) {
                    $q->where('first_name', 'ilike', "%{$search}%")
                        ->orWhere('last_name', 'ilike', "%{$search}%")
                        ->orWhere('email', 'ilike', "%{$search}%");
                });
            })
            ->when($request->relationship, fn($q, $rel) => $q->where('relationship', $rel));

        $sortField = $request->get('sort', 'created_at');
        $sortDirection = $request->get('direction', 'desc');
        $query->orderBy($sortField, $sortDirection);

        $perPage = $request->get('per_page', 15);
        $parents = $query->paginate($perPage);

        return $this->success(ParentResource::collection($parents)->response()->getData(true));
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            // User data
            'username' => ['nullable', 'string', 'min:3', 'max:50', 'unique:users,username', 'alpha_dash'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
            'password' => ['nullable', 'string', 'min:8'],
            'first_name' => ['required', 'string', 'max:100'],
            'last_name' => ['nullable', 'string', 'max:100'],

            // Parent data
            'relationship' => ['required', 'in:father,mother,guardian'],
            'phone' => ['nullable', 'string', 'max:20'],
            'occupation' => ['nullable', 'string', 'max:100'],
            'income' => ['nullable', 'numeric', 'min:0'],
            'address' => ['nullable', 'string', 'max:500'],
            'is_primary_contact' => ['boolean'],

            // Link to students
            'student_ids' => ['nullable', 'array'],
            'student_ids.*' => ['uuid', 'exists:students,id'],
        ]);

        try {
            DB::beginTransaction();

            // Create user account
            $user = User::create([
                'username' => $data['username'] ?? Str::slug($data['first_name'] . '-' . Str::random(4)),
                'email' => $data['email'],
                'password' => Hash::make($data['password'] ?? 'password123'),
                'first_name' => $data['first_name'],
                'last_name' => $data['last_name'] ?? null,
                'phone' => $data['phone'] ?? null,
                'is_active' => true,
            ]);

            // Assign parent role
            $user->assignRole('wali_murid');

            // Create parent
            $parent = StudentParent::create([
                'user_id' => $user->id,
                'relationship' => $data['relationship'],
                'phone' => $data['phone'] ?? null,
                'occupation' => $data['occupation'] ?? null,
                'income' => $data['income'] ?? null,
                'address' => $data['address'] ?? null,
                'is_primary_contact' => $data['is_primary_contact'] ?? false,
            ]);

            // Link to students
            if (!empty($data['student_ids'])) {
                $parent->students()->attach($data['student_ids']);
            }

            DB::commit();

            $parent->load(['user', 'students.user']);

            return $this->success(
                new ParentResource($parent),
                'Orang tua/wali berhasil ditambahkan',
                201
            );
        } catch (\Exception $e) {
            DB::rollBack();

            return $this->error('Gagal menambahkan orang tua/wali: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(StudentParent $parent): JsonResponse
    {
        $parent->load(['user', 'students.user']);

        return $this->success(new ParentResource($parent));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, StudentParent $parent): JsonResponse
    {
        $data = $request->validate([
            // User data
            'email' => ['sometimes', 'string', 'email', 'max:255', 'unique:users,email,' . $parent->user_id],
            'first_name' => ['sometimes', 'string', 'max:100'],
            'last_name' => ['nullable', 'string', 'max:100'],

            // Parent data
            'relationship' => ['sometimes', 'in:father,mother,guardian'],
            'phone' => ['nullable', 'string', 'max:20'],
            'occupation' => ['nullable', 'string', 'max:100'],
            'income' => ['nullable', 'numeric', 'min:0'],
            'address' => ['nullable', 'string', 'max:500'],
            'is_primary_contact' => ['boolean'],

            // Link to students
            'student_ids' => ['nullable', 'array'],
            'student_ids.*' => ['uuid', 'exists:students,id'],
        ]);

        try {
            DB::beginTransaction();

            // Update user data
            $userData = array_filter([
                'first_name' => $data['first_name'] ?? null,
                'last_name' => $data['last_name'] ?? null,
                'email' => $data['email'] ?? null,
                'phone' => $data['phone'] ?? null,
            ]);

            if (!empty($userData)) {
                $parent->user->update($userData);
            }

            // Remove user-related fields
            unset($data['first_name'], $data['last_name'], $data['email']);

            // Handle student sync
            if (isset($data['student_ids'])) {
                $parent->students()->sync($data['student_ids']);
                unset($data['student_ids']);
            }

            // Update parent
            $parent->update($data);

            DB::commit();

            $parent->load(['user', 'students.user']);

            return $this->success(new ParentResource($parent), 'Orang tua/wali berhasil diperbarui');
        } catch (\Exception $e) {
            DB::rollBack();

            return $this->error('Gagal memperbarui orang tua/wali: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(StudentParent $parent): JsonResponse
    {
        try {
            DB::beginTransaction();

            $parent->students()->detach();
            $parent->user->delete();
            $parent->delete();

            DB::commit();

            return $this->success(null, 'Orang tua/wali berhasil dihapus');
        } catch (\Exception $e) {
            DB::rollBack();

            return $this->error('Gagal menghapus orang tua/wali: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Get children of a parent.
     */
    public function children(StudentParent $parent): JsonResponse
    {
        $children = $parent->students()->with('user')->get();

        return $this->success($children);
    }
}
