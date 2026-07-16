<?php

namespace App\Http\Controllers\Api\V1\Auth;

use App\Http\Controllers\Api\ApiController;
use App\Http\Requests\Auth\UpdateProfileRequest;
use App\Http\Resources\UserResource;
use App\Infrastructure\Persistence\Eloquent\Auth\UserProfile;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class ProfileController extends ApiController
{
    /**
     * Get current user profile.
     */
    public function show(Request $request): JsonResponse
    {
        $user = $request->user()->load('roles');

        return $this->success(new UserResource($user), 'Profile retrieved successfully');
    }

    /**
     * Update current user profile.
     */
    public function update(UpdateProfileRequest $request): JsonResponse
    {
        $user = $request->user();
        $data = $request->validated();
        $profileData = array_intersect_key($data, array_flip(['first_name', 'last_name', 'phone']));
        $userData = array_intersect_key($data, array_flip(['username', 'email']));

        // Handle avatar upload
        if ($request->hasFile('avatar')) {
            // Delete old avatar if exists
            if ($user->avatar) {
                Storage::disk('public')->delete($user->avatar);
            }

            $userData['avatar'] = $request->file('avatar')->store('avatars', 'public');
        }

        $user->update($userData);

        UserProfile::query()->updateOrCreate(
            ['user_id' => $user->id],
            $profileData
        );

        $user->load('roles');

        return $this->success(new UserResource($user), 'Profile updated successfully');
    }

    /**
     * Delete avatar.
     */
    public function deleteAvatar(Request $request): JsonResponse
    {
        $user = $request->user();

        if ($user->avatar) {
            Storage::disk('public')->delete($user->avatar);
            $user->update(['avatar' => null]);
        }

        return $this->success(null, 'Avatar deleted successfully');
    }
}
