<?php

namespace App\Enums;

enum StaffRole: string
{
    case Administrator = 'administrator';
    case Manager = 'manager';
    case Staff = 'staff';
}
