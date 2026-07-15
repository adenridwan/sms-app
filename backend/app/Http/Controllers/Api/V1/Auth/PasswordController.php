<?php

namespace App\Http\Controllers\Api\V1\Auth;

use App\Http\Controllers\Api\ApiController;
use App\Http\Requests\Auth\ChangePasswordRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Hash;

class PasswordController extends ApiController
{
    /**
     * Change current user password.
     */
    public function change(ChangePasswordRequest $request): JsonResponse
    {
        $user = $request->user();

        // Verify current password
        if (!Hash::check($request->current_password, $user->password)) {
            return $this->error('Password saat ini tidak valid', 422, [
                'current_password' => ['Password saat ini tidak valid.'],
            ]);
        }

        // Update password
        $user->update([
            'password' => Hash::make($request->password),
        ]);

        return $this->success(null, 'Password berhasil diubah');
    }
}
