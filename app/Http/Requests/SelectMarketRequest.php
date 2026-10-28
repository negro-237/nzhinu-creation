<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SelectMarketRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return ['code' => ['required', 'string', 'size:2', Rule::in(config('storefront.markets')), Rule::exists('markets', 'code')->where('is_active', true)], 'clear_cart' => ['nullable', 'boolean']];
    }

    public function messages(): array
    {
        return [
            'code.required' => __('shop.market_error'), 'code.string' => __('shop.market_error'),
            'code.size' => __('shop.market_error'), 'code.exists' => __('shop.market_error'),
            'code.in' => __('shop.market_error'),
            'clear_cart.boolean' => __('shop.switch_warning'),
        ];
    }
}
