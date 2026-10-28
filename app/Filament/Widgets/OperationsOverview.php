<?php

namespace App\Filament\Widgets;

use App\Enums\StaffPermission;
use App\Services\OperationsDashboard;
use Filament\Widgets\Widget;

class OperationsOverview extends Widget
{
    protected string $view = 'filament.widgets.operations-overview';

    protected int|string|array $columnSpan = 'full';

    protected static bool $isLazy = false;

    public static function canView(): bool
    {
        return auth()->user()?->can(StaffPermission::ViewDashboard->value) ?? false;
    }

    protected function getViewData(): array
    {
        return ['report' => app(OperationsDashboard::class)->report(auth()->user())];
    }
}
