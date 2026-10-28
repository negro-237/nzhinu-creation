<?php

namespace App\Policies;

use App\Enums\StaffPermission;
use App\Models\User;

class InventoryReservationPolicy extends InventoryPolicy
{
    public function create(User $user): bool
    {
        return $user->can(StaffPermission::ManageReservations->value);
    }
}
