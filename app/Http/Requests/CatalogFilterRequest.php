<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CatalogFilterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'q' => ['nullable', 'string', 'max:100'],
            'category' => ['nullable', 'string', 'max:255', 'regex:/\A[a-z0-9-]+\z/'],
            'collection' => ['nullable', 'string', 'max:255', 'regex:/\A[a-z0-9-]+\z/'],
            'min_price' => ['nullable', 'string', 'max:20'], 'max_price' => ['nullable', 'string', 'max:20'],
            'in_stock' => ['nullable', 'boolean'],
            'sort' => ['nullable', Rule::in(['newest', 'price_asc', 'price_desc', 'name'])],
            'page' => ['nullable', 'integer', 'min:1', 'max:10000'],
        ];
    }

    public function messages(): array
    {
        return [
            'string' => 'Le champ :attribute doit être un texte.',
            'max' => 'Le champ :attribute dépasse la limite autorisée.',
            'regex' => 'La valeur du champ :attribute n’est pas valide.',
            'boolean' => 'Choisissez une valeur valide pour :attribute.',
            'integer' => 'Le champ :attribute doit être un entier.',
            'min' => 'La valeur du champ :attribute est trop petite.',
            'in' => 'Le tri demandé n’est pas disponible.',
        ];
    }

    public function attributes(): array
    {
        return ['q' => 'recherche', 'category' => 'catégorie', 'collection' => 'collection',
            'min_price' => 'prix minimum', 'max_price' => 'prix maximum', 'in_stock' => 'en stock', 'sort' => 'tri', 'page' => 'page'];
    }
}
