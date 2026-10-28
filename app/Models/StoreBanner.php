<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class StoreBanner extends Model
{
    use SoftDeletes;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['is_published' => 'boolean', 'version' => 'integer', 'sort_order' => 'integer', 'starts_at' => 'datetime', 'ends_at' => 'datetime'];
    }
}
