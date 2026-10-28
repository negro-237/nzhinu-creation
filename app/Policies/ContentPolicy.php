<?php

namespace App\Policies;

use App\Enums\StaffPermission;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class ContentPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can(StaffPermission::ViewContent->value);
    }

    public function view(User $user, Model $record): bool
    {
        return $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return $user->can(StaffPermission::ManageContent->value);
    }

    public function update(User $user, Model $record): bool
    {
        return $this->create($user);
    }

    public function delete(User $user, Model $record): bool
    {
        return false;
    }
}
