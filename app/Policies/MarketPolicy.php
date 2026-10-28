<?php

namespace App\Policies;

use App\Enums\StaffPermission;
use App\Models\User;

class MarketPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can(StaffPermission::ManageMarkets->value);
    }

    public function create(User $user): bool
    {
        return $user->can(StaffPermission::ManageMarkets->value);
    }

    // Market identity/currency must remain stable for historical prices and orders.
    // Editing and deletion are deliberately unavailable in phase 1.
}
