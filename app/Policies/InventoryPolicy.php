<?php

namespace App\Policies;

use App\Enums\StaffPermission;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class InventoryPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can(StaffPermission::ViewInventory->value);
    }

    public function view(User $user, Model $record): bool
    {
        return $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return $user->can(StaffPermission::ManageInventory->value);
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
