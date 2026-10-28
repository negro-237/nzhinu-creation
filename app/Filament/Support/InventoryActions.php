<?php

namespace App\Filament\Support;

use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Illuminate\Validation\ValidationException;

class InventoryActions
{
    public static function run(Action $action, callable $callback): mixed
    {
        try {
            return $callback();
        } catch (ValidationException $exception) {
            Notification::make()->danger()->title('Opération refusée')->body($exception->validator->errors()->first())->send();
            $prefix = 'mountedActions.'.$action->getNestingIndex().'.data.';
            $errors = [];
            foreach ($exception->errors() as $field => $messages) {
                $errors[$prefix.explode('.', $field)[0]] = $messages;
            }
            throw ValidationException::withMessages($errors);
        }
    }
}
