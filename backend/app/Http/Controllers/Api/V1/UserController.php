<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\ApiController;
use App\Http\Resources\UserResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Spatie\Permission\Models\Role;

class UserController extends ApiController
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request): JsonResponse
    {
        $query = User::with('roles')
            ->when($request->search, function ($q, $search) {
                $q->where(function ($query) use ($search) {
                    $query->where('username', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%")
                        ->orWhere('first_name', 'like', "%{$search}%")
                        ->orWhere('last_name', 'like', "%{$search}%");
                });
            })
            ->when($request->role, fn($q, $role) => $q->role($role))
            ->when($request->has('is_active'), fn($q) => $q->where('is_active', $request->boolean('is_active')));

        $sortField = $request->get('sort', 'created_at');
        $sortDirection = $request->get('direction', 'desc');
        $query->orderBy($sortField, $sortDirection);

        $perPage = $request->get('per_page', 15);
        $users = $query->paginate($perPage);

        return $this->success(UserResource::collection($users)->response()->getData(true));
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'username' => ['required', 'string', 'min:3', 'max:50', 'unique:users,username', 'alpha_dash'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8'],
            'first_name' => ['required', 'string', 'max:100'],
            'last_name' => ['nullable', 'string', 'max:100'],
            'phone' => ['nullable', 'string', 'max:20'],
            'is_active' => ['boolean'],
            'roles' => ['nullable', 'array'],
            'roles.*' => ['string', 'exists:roles,name'],
        ]);

        $data['password'] = Hash::make($data['password']);

        $user = User::create($data);

        // Assign roles
        if (!empty($data['roles'])) {
            $user->syncRoles($data['roles']);
        }

        $user->load('roles');

        return $this->success(
            new UserResource($user),
            'User berhasil ditambahkan',
            201
        );
    }

    /**
     * Display the specified resource.
     */
    public function show(User $user): JsonResponse
    {
        $user->load('roles');

        return $this->success(new UserResource($user));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, User $user): JsonResponse
    {
        $data = $request->validate([
            'username' => [
                'sometimes',
                'string',
                'min:3',
                'max:50',
                'alpha_dash',
                Rule::unique('users', 'username')->ignore($user->id),
            ],
            'email' => [
                'sometimes',
                'string',
                'email',
                'max:255',
                Rule::unique('users', 'email')->ignore($user->id),
            ],
            'password' => ['nullable', 'string', 'min:8'],
            'first_name' => ['sometimes', 'string', 'max:100'],
            'last_name' => ['nullable', 'string', 'max:100'],
            'phone' => ['nullable', 'string', 'max:20'],
            'is_active' => ['boolean'],
            'roles' => ['nullable', 'array'],
            'roles.*' => ['string', 'exists:roles,name'],
        ]);

        if (!empty($data['password'])) {
            $data['password'] = Hash::make($data['password']);
        } else {
            unset($data['password']);
        }

        // Handle roles
        if (isset($data['roles'])) {
            $user->syncRoles($data['roles']);
            unset($data['roles']);
        }

        $user->update($data);
        $user->load('roles');

        return $this->success(new UserResource($user), 'User berhasil diperbarui');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(User $user): JsonResponse
    {
        // Prevent deleting self
        if ($user->id === auth()->id()) {
            return $this->error('Tidak dapat menghapus akun sendiri', 422);
        }

        // Soft delete
        $user->delete();

        return $this->success(null, 'User berhasil dihapus');
    }

    /**
     * Get available roles.
     */
    public function roles(): JsonResponse
    {
        $roles = Role::all()->map(fn($role) => [
            'id' => $role->id,
            'name' => $role->name,
            'guard_name' => $role->guard_name,
        ]);

        return $this->success($roles);
    }

    /**
     * Toggle user active status.
     */
    public function toggleActive(User $user): JsonResponse
    {
        if ($user->id === auth()->id()) {
            return $this->error('Tidak dapat menonaktifkan akun sendiri', 422);
        }

        $user->update(['is_active' => !$user->is_active]);
        $user->load('roles');

        $status = $user->is_active ? 'diaktifkan' : 'dinonaktifkan';

        return $this->success(new UserResource($user), "User berhasil {$status}");
    }
}
