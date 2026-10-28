<?php

namespace App\Models;

use App\Enums\ReservationStatus;
use Illuminate\Database\Eloquent\Model;

class ReservationHistory extends Model
{
    public $timestamps = false;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['from_status' => ReservationStatus::class, 'to_status' => ReservationStatus::class, 'created_at' => 'datetime'];
    }
}
