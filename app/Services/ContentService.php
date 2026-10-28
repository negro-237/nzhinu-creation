<?php

namespace App\Services;

use App\Enums\StaffPermission;
use App\Models\CmsPage;
use App\Models\Market;
use App\Models\StoreBanner;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class ContentService
{
    public function savePage(User $actor, array $data, ?int $id = null): CmsPage
    {
        Gate::forUser($actor)->authorize(StaffPermission::ManageContent->value);
        $input = Validator::make($data, [...$this->commonRules(),
            'slug' => ['required', 'string', 'max:120', 'regex:/\A[a-z0-9]+(?:-[a-z0-9]+)*\z/', Rule::unique('cms_pages', 'slug')->ignore($id)],
            'body' => ['required', 'string', 'max:20000'], 'meta_description' => ['required', 'string', 'max:255']])->validate();

        return $this->save($actor, CmsPage::class, $input, $id);
    }

    public function saveBanner(User $actor, array $data, ?int $id = null): StoreBanner
    {
        Gate::forUser($actor)->authorize(StaffPermission::ManageContent->value);
        $input = Validator::make($data, [...$this->commonRules(), 'body' => ['required', 'string', 'max:2000'],
            'link_path' => ['nullable', 'string', 'max:255', 'regex:~\A/(?:boutique|pages/[a-z0-9]+(?:-[a-z0-9]+)*|produits/[a-z0-9]+(?:-[a-z0-9]+)*)\z~'],
            'link_label' => ['nullable', 'required_with:link_path', 'string', 'max:80'],
            'starts_at' => ['nullable', 'date'], 'ends_at' => ['nullable', 'date'],
            'sort_order' => ['required', 'integer', 'min:0', 'max:10000']])->validate();
        if (! ($input['link_path'] ?? null)) {
            $input['link_label'] = null;
        }
        if (($input['starts_at'] ?? null) && ($input['ends_at'] ?? null) && strtotime($input['ends_at']) <= strtotime($input['starts_at'])) {
            throw ValidationException::withMessages(['ends_at' => 'La fin doit être après le début.']);
        }

        return $this->save($actor, StoreBanner::class, $input, $id);
    }

    private function commonRules(): array
    {
        return ['market_id' => ['nullable', 'integer', Rule::exists('markets', 'id')], 'title' => ['required', 'string', 'max:160'],
            'is_published' => ['required', 'boolean'], 'version' => ['sometimes', 'required', 'integer', 'min:0']];
    }

    private function save(User $actor, string $model, array $input, ?int $id): Model
    {
        return DB::transaction(function () use ($actor, $model, $input, $id) {
            $record = $id ? $model::whereKey($id)->lockForUpdate()->firstOrFail() : new $model;
            Gate::forUser($actor)->authorize($id ? 'update' : 'create', $record);
            if ($id && (! isset($input['version']) || $input['version'] !== $record->version)) {
                throw ValidationException::withMessages(['version' => 'Le contenu a changé. Rechargez le formulaire.']);
            }
            $input['market_id'] = $input['market_id'] ?? null;
            if ($input['is_published'] && $input['market_id'] && ! Market::whereKey($input['market_id'])->where('is_active', true)->exists()) {
                throw ValidationException::withMessages(['market_id' => 'Le marché doit être actif pour publier.']);
            }
            unset($input['version']);
            $record->fill($input);
            $record->version = $id ? $record->version + 1 : 0;
            $record->save();

            return $record;
        }, attempts: 3);
    }

    public function archive(User $actor, CmsPage|StoreBanner $record, int $version): void
    {
        DB::transaction(function () use ($actor, $record, $version) {
            $locked = $record::whereKey($record->id)->lockForUpdate()->firstOrFail();
            Gate::forUser($actor)->authorize('update', $locked);
            if ($locked->version !== $version) {
                throw ValidationException::withMessages(['version' => 'Le contenu a changé. Rechargez la liste.']);
            }
            $locked->update(['is_published' => false, 'version' => $locked->version + 1]);
            $locked->delete();
        });
    }

    private function visible(string $model, Market $market)
    {
        return $model::where('is_published', true)->where(fn ($q) => $q->whereNull('market_id')->orWhere('market_id', $market->id));
    }

    public function page(Market $market, string $slug): CmsPage
    {
        return $this->visible(CmsPage::class, $market)->where('slug', $slug)->firstOrFail();
    }

    public function navigation(Market $market): array
    {
        return $this->visible(CmsPage::class, $market)->orderBy('title')->limit(8)->get(['title', 'slug'])->toArray();
    }

    public function banners(Market $market): array
    {
        return $this->visible(StoreBanner::class, $market)
            ->where(fn ($q) => $q->whereNull('starts_at')->orWhere('starts_at', '<=', now()))
            ->where(fn ($q) => $q->whereNull('ends_at')->orWhere('ends_at', '>', now()))
            ->orderBy('sort_order')->orderBy('id')->limit(3)->get(['id', 'title', 'body', 'link_path', 'link_label'])->toArray();
    }
}
