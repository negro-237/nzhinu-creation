<?php

namespace App\Enums;

enum StaffPermission: string
{
    case AccessAdmin = 'access admin';
    case ManageStaff = 'manage staff';
    case ManageMarkets = 'manage markets';
    case ManageStockLocations = 'manage stock locations';
    case ViewCatalog = 'view catalog';
    case ManageCatalog = 'manage catalog';
    case ViewInventory = 'view inventory';
    case ManageInventory = 'manage inventory';
    case ManageReservations = 'manage reservations';
    case ViewOrders = 'view orders';
    case ManageOrders = 'manage orders';
    case ManageDeliveryOptions = 'manage delivery options';
    case RecordPayments = 'record payments';
    case VerifyPayments = 'verify payments';
    case ManageRefunds = 'manage refunds';
    case FulfillOrders = 'fulfill orders';
    case ViewContent = 'view content';
    case ManageContent = 'manage content';
    case ViewDashboard = 'view dashboard';
}
