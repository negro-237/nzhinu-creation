<?php

namespace App\Notifications;

use App\Enums\StaffPermission;
use App\Models\User;
use Filament\Notifications\Notification as FilamentNotification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

class LowStockNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public int $inventoryId, public string $sku, public string $location, public int $available)
    {
        $this->afterCommit();
    }

    public function via(object $notifiable): array
    {
        return $this->shouldSend($notifiable, 'database') ? ['database'] : [];
    }

    public function shouldSend(object $notifiable, string $channel): bool
    {
        // Queued notifications already carry their channel: via() alone is not reevaluated by the worker.
        $current = $notifiable instanceof User ? User::find($notifiable->id, ['id']) : null;

        return $channel === 'database' && $current && $current->can(StaffPermission::AccessAdmin->value)
            && $current->can(StaffPermission::ManageInventory->value);
    }

    public function toDatabase(object $notifiable): array
    {
        return [...FilamentNotification::make()->title('Stock faible')->warning()
            ->body(e($this->sku).' — '.e($this->location).' : '.$this->available.' unité(s) disponible(s).')
            ->getDatabaseMessage(), 'inventory_id' => $this->inventoryId];
    }
}
