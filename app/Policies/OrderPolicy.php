<?php

namespace App\Policies;

use App\Enums\StaffPermission;
use App\Models\Order;
use App\Models\User;

class OrderPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can(StaffPermission::ViewOrders->value);
    }

    public function view(User $user, Order $order): bool
    {
        return $this->viewAny($user);
    }

    public function update(User $user, Order $order): bool
    {
        return $user->can(StaffPermission::ManageOrders->value);
    }

    public function create(User $user): bool
    {
        return false;
    }

    public function delete(User $user, Order $order): bool
    {
        return false;
    }
}
