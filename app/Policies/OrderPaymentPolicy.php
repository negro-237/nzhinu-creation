<?php

namespace App\Policies;

use App\Enums\StaffPermission;
use App\Models\OrderPayment;
use App\Models\User;

class OrderPaymentPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can(StaffPermission::ViewOrders->value);
    }

    public function view(User $user, OrderPayment $payment): bool
    {
        return $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return false;
    }

    public function update(User $user, OrderPayment $payment): bool
    {
        return false;
    }

    public function delete(User $user, OrderPayment $payment): bool
    {
        return false;
    }
}
