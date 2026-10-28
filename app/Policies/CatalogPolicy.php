<?php

namespace App\Policies;

use App\Enums\StaffPermission;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class CatalogPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can(StaffPermission::ViewCatalog->value);
    }

    public function view(User $user, Model $record): bool
    {
        return $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return $user->can(StaffPermission::ManageCatalog->value);
    }

    public function update(User $user, Model $record): bool
    {
        return $this->create($user) && ! $record->trashed();
    }

    public function delete(User $user, Model $record): bool
    {
        return $this->update($user, $record);
    }

    public function forceDelete(User $user, Model $record): bool
    {
        return false;
    }
}
