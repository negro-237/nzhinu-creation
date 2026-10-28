<?php

namespace App\Services;

use App\Models\Category;
use App\Models\CulturalCollection;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class TaxonomyService
{
    /** @param class-string<Category|CulturalCollection> $model */
    public function save(User $actor, string $model, array $data, ?Model $record = null): Model
    {
        abort_unless(in_array($model, [Category::class, CulturalCollection::class], true), 422);
        Gate::forUser($actor)->authorize($record ? 'update' : 'create', $record ?? $model);
        $validated = Validator::make($data, [
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['required', 'string', 'max:255', 'regex:/\A[a-z0-9]+(?:-[a-z0-9]+)*\z/', Rule::unique((new $model)->getTable(), 'slug')->ignore($record?->id)],
            'description' => ['nullable', 'string', 'max:10000'],
            'is_active' => ['required', 'boolean'],
            ...($model === Category::class ? ['icon' => ['nullable', Rule::in(array_keys(config('catalog.category_icons')))]] : []),
        ])->validate();

        return DB::transaction(function () use ($actor, $model, $record, $validated) {
            if ($record) {
                $record = $model::query()->lockForUpdate()->findOrFail($record->id);
                Gate::forUser($actor)->authorize('update', $record);
                $record->update($validated);
            } else {
                $record = $model::create($validated);
            }

            return $record;
        });
    }

    public function archive(User $actor, Category|CulturalCollection $record): bool
    {
        Gate::forUser($actor)->authorize('delete', $record);

        return DB::transaction(function () use ($record) {
            $record = $record::query()->lockForUpdate()->findOrFail($record->id);

            return (bool) $record->delete();
        });
    }
}
