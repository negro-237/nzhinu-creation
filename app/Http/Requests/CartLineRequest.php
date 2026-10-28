<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CartLineRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Guest cart belongs exclusively to the current Laravel session.
    }

    public function rules(): array
    {
        return ['inventory_id' => ['required', 'integer', 'min:1'], 'quantity' => ['required', 'integer', 'min:1', 'max:99']];
    }

    public function messages(): array
    {
        return [
            'inventory_id.required' => __('shop.choose_stock'),
            'inventory_id.integer' => __('shop.unavailable_error'), 'inventory_id.min' => __('shop.unavailable_error'),
            'quantity.required' => __('shop.quantity_error'), 'quantity.integer' => __('shop.quantity_error'),
            'quantity.min' => __('shop.quantity_error'), 'quantity.max' => __('shop.quantity_error'),
        ];
    }
}
