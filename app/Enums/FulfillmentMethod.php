<?php

namespace App\Enums;

enum FulfillmentMethod: string
{
    case Delivery = 'delivery';
    case Pickup = 'pickup';
}
