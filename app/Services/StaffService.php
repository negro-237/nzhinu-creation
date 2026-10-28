<?php

namespace App\Services;

use App\Enums\StaffPermission;
use App\Enums\StaffRole;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class StaffService
{
    public function create(User $actor, array $data): User
    {
        Gate::forUser($actor)->authorize(StaffPermission::ManageStaff->value);

        return $this->createForServerOperator($data);
    }

    /** Only for a trusted operator with access to the server's Artisan CLI. */
    public function createForServerOperator(array $data): User
    {
        $validated = Validator::make($data, [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', Password::min(12)->letters()->mixedCase()->numbers()->symbols()],
            'role' => ['required', Rule::enum(StaffRole::class)],
        ])->validate();

        return DB::transaction(function () use ($validated) {
            $user = User::create(collect($validated)->only(['name', 'email', 'password'])->all());
            $user->assignRole($validated['role']);

            return $user;
        });
    }
}
