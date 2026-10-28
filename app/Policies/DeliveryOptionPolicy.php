<?php

namespace App\Policies;

use App\Enums\StaffPermission;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class DeliveryOptionPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can(StaffPermission::ManageDeliveryOptions->value);
    }

    public function create(User $user): bool
    {
        return $this->viewAny($user);
    }

    public function update(User $user, Model $model): bool
    {
        return $this->viewAny($user);
    }

    public function delete(User $user, Model $model): bool
    {
        return $this->viewAny($user);
    }
}
