<?php

namespace App\Filament\Resources\StoreBannerResource\Pages;

use App\Filament\Resources\StoreBannerResource;
use Filament\Resources\Pages\ManageRecords;

class ManageBanners extends ManageRecords
{
    protected static string $resource = StoreBannerResource::class;

    protected function getHeaderActions(): array
    {
        return [StoreBannerResource::createAction()];
    }
}
