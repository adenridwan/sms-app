<?php

namespace App\Http\Policies;

use App\Infrastructure\Persistence\Eloquent\Auth\User;
use App\Infrastructure\Persistence\Eloquent\Student\Student;

/**
 * Akses detail siswa (R7, ROLE-ACCESS-PLAN.md): siapa pun yang menebak
 * URL/API detail siswa di luar cakupannya harus ditolak (403), meski
 * baris itu tidak muncul di daftar.
 *
 * Semua keputusan memakai ulang scope Student::visibleTo sehingga daftar
 * dan detail tidak pernah berbeda aturan. Super admin lolos otomatis via
 * Gate::before di AuthServiceProvider.
 */
class StudentPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo('students.view');
    }

    public function view(User $user, Student $student): bool
    {
        return $this->isVisible($user, $student);
    }

    public function update(User $user, Student $student): bool
    {
        return $user->hasPermissionTo('students.update')
            && $this->isVisible($user, $student);
    }

    public function delete(User $user, Student $student): bool
    {
        return $user->hasPermissionTo('students.delete')
            && $this->isVisible($user, $student);
    }

    private function isVisible(User $user, Student $student): bool
    {
        return Student::query()
            ->visibleTo($user)
            ->whereKey($student->getKey())
            ->exists();
    }
}
