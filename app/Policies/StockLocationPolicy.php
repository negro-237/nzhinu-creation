<?php

namespace App\Policies;

use App\Enums\StaffPermission;
use App\Models\StockLocation;
use App\Models\User;

class StockLocationPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can(StaffPermission::ManageStockLocations->value);
    }

    public function view(User $user, StockLocation $location): bool
    {
        return $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return $this->viewAny($user);
    }

    public function update(User $user, StockLocation $location): bool
    {
        return $this->viewAny($user);
    }

    public function delete(User $user, StockLocation $location): bool
    {
        return $this->viewAny($user);
    }
}
