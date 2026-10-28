<?php

namespace App\Support;

use Filament\AvatarProviders\Contracts\AvatarProvider;
use Illuminate\Database\Eloquent\Model;

class StaffAvatar implements AvatarProvider
{
    public function get(Model $record): string
    {
        $initial = htmlspecialchars(mb_strtoupper(mb_substr(trim((string) $record->getAttribute('name')), 0, 1)) ?: 'N', ENT_QUOTES | ENT_XML1, 'UTF-8');

        return 'data:image/svg+xml;base64,'.base64_encode('<svg xmlns="http://www.w3.org/2000/svg" width="64" height="64" viewBox="0 0 64 64"><rect width="64" height="64" rx="32" fill="#111111"/><text x="32" y="43" text-anchor="middle" font-family="Arial" font-size="32" fill="#ffb511">'.$initial.'</text></svg>');
    }
}
