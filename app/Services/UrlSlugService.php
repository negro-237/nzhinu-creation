<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class UrlSlugService
{
    public function generate(string $name, string $table, int $maxLength = 255): string
    {
        $base = rtrim(substr(Str::slug($name), 0, $maxLength), '-') ?: 'element';
        $slug = $base;
        $number = 2;

        // Archived records also reserve their URLs.
        while (DB::table($table)->where('slug', $slug)->exists()) {
            $suffix = '-'.$number++;
            $slug = rtrim(substr($base, 0, $maxLength - strlen($suffix)), '-').$suffix;
        }

        return $slug;
    }
}
