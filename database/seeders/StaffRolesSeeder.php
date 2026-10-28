<?php

namespace Database\Seeders;

use App\Enums\StaffPermission;
use App\Enums\StaffRole;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class StaffRolesSeeder extends Seeder
{
    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach (StaffPermission::cases() as $permission) {
            Permission::findOrCreate($permission->value, 'web');
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach (StaffRole::cases() as $role) {
            $permissions = match ($role) {
                StaffRole::Administrator => StaffPermission::cases(),
                StaffRole::Manager => [StaffPermission::AccessAdmin, StaffPermission::ManageMarkets, StaffPermission::ManageStockLocations,
                    StaffPermission::ViewCatalog, StaffPermission::ManageCatalog, StaffPermission::ViewInventory,
                    StaffPermission::ManageInventory, StaffPermission::ManageReservations, StaffPermission::ViewOrders,
                    StaffPermission::ManageOrders, StaffPermission::ManageDeliveryOptions, StaffPermission::RecordPayments, StaffPermission::FulfillOrders,
                    StaffPermission::ViewContent, StaffPermission::ManageContent, StaffPermission::ViewDashboard],
                StaffRole::Staff => [StaffPermission::AccessAdmin, StaffPermission::ViewCatalog, StaffPermission::ViewInventory],
            };
            Role::findOrCreate($role->value, 'web')->syncPermissions(
                array_map(fn (StaffPermission $permission) => $permission->value, $permissions),
            );
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
