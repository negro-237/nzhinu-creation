<?php

namespace App\Services;

use App\Models\Product;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

class ProductMediaService
{
    public function add(User $actor, Product $product, UploadedFile $image, string $alt): Media
    {
        Gate::forUser($actor)->authorize('update', $product);
        Validator::make(['image' => $image, 'alt' => $alt], [
            'image' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120', 'dimensions:max_width=6000,max_height=6000'],
            'alt' => ['required', 'string', 'max:255'],
        ])->validate();

        return $product->addMedia($image)->usingFileName(Str::uuid().'.'.$image->guessExtension())
            ->withCustomProperties(['alt' => $alt])->toMediaCollection('images');
    }

    public function remove(User $actor, Product $product, int $mediaId): bool
    {
        Gate::forUser($actor)->authorize('update', $product);
        $media = $product->media()->where('collection_name', 'images')->findOrFail($mediaId);

        return (bool) $media->delete();
    }
}
