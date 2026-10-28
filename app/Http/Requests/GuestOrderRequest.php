<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Validation\Rule;

class GuestOrderRequest extends FormRequest
{
    protected function failedValidation(Validator $validator)
    {
        // Inertia preserves form fields in memory; do not duplicate guest PII in session old input.
        throw new HttpResponseException(to_route('checkout.show')->withErrors($validator));
    }

    public function authorize(): bool
    {
        return true;
    }

    public static function inputRules(): array
    {
        return [
            'request_key' => ['required', 'uuid'], 'customer_name' => ['required', 'string', 'max:255'],
            'phone' => ['required', 'string', 'max:30', 'regex:/\A\+?[0-9() .-]{8,30}\z/'],
            'email' => ['nullable', 'email', 'max:255'], 'method' => ['required', Rule::in(['delivery', 'pickup'])],
            'pickup_point_id' => ['nullable', 'required_if:method,pickup', 'integer', 'min:1'],
            'delivery_zone_id' => ['nullable', 'integer', 'min:1'],
            'street' => ['nullable', 'required_if:method,delivery', 'string', 'max:255'],
            'street2' => ['nullable', 'string', 'max:255'], 'city' => ['nullable', 'required_if:method,delivery', 'string', 'max:100'],
            'region' => ['nullable', 'string', 'max:100'], 'postal_code' => ['nullable', 'string', 'max:30'],
        ];
    }

    public function rules(): array
    {
        return self::inputRules();
    }

    public function messages(): array
    {
        return ['required' => 'Le champ :attribute est obligatoire.', 'required_if' => 'Le champ :attribute est obligatoire pour ce mode.',
            'regex' => 'Le numéro de téléphone n’est pas valide.', 'email' => 'L’adresse e-mail n’est pas valide.', 'uuid' => 'Rechargez le formulaire avant de réessayer.'];
    }

    public function attributes(): array
    {
        return ['customer_name' => 'nom', 'phone' => 'téléphone', 'street' => 'adresse', 'city' => 'ville', 'pickup_point_id' => 'point de retrait'];
    }
}
