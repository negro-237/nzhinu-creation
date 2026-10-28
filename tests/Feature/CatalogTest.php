<?php

namespace Tests\Feature;

use App\Enums\Currency;
use App\Enums\ProductStatus;
use App\Enums\StaffRole;
use App\Filament\Resources\CategoryResource;
use App\Filament\Resources\CategoryResource\Pages\CreateCategory;
use App\Filament\Resources\CategoryResource\Pages\EditCategory;
use App\Filament\Resources\CulturalCollectionResource;
use App\Filament\Resources\CulturalCollectionResource\Pages\CreateCulturalCollection;
use App\Filament\Resources\ProductResource;
use App\Filament\Resources\ProductResource\Pages\CreateProduct;
use App\Filament\Resources\ProductResource\Pages\EditProduct;
use App\Filament\Resources\ProductResource\Pages\ListProducts;
use App\Models\Category;
use App\Models\CulturalCollection;
use App\Models\Market;
use App\Models\MarketPrice;
use App\Models\Product;
use App\Models\User;
use App\Services\CatalogService;
use App\Services\ProductMediaService;
use App\Services\TaxonomyService;
use Database\Seeders\DatabaseSeeder;
use Filament\Facades\Filament;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class CatalogTest extends TestCase
{
    use RefreshDatabase;

    private User $manager;

    private Category $category;

    private Market $ca;

    private Market $cm;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
        $this->manager = User::factory()->create();
        $this->manager->assignRole(StaffRole::Manager);
        $this->category = Category::where('slug', 'vetements')->firstOrFail();
        $this->ca = Market::where('code', 'CA')->firstOrFail();
        $this->cm = Market::where('code', 'CM')->firstOrFail();
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $this->actingAs($this->manager);
    }

    public function test_default_variant_is_created_when_no_options_are_supplied(): void
    {
        $data = $this->data();
        unset($data['variants']);
        $product = $this->save($data);
        $this->assertSame(ProductStatus::Draft, $product->status);
        $this->assertCount(1, $product->variants);
        $this->assertSame('Standard', $product->variants->first()->label);
        $this->assertNotEmpty($product->variants->first()->sku);
    }

    public function test_prices_are_exact_and_market_publication_is_independent(): void
    {
        $collection = CulturalCollection::factory()->create();
        $product = $this->save($this->data([
            'status' => 'published', 'collections' => [$collection->id],
            'markets' => [['market_id' => $this->ca->id, 'is_available' => true]],
        ]));
        $prices = $product->variants->first()->prices->keyBy('market_id');
        $this->assertSame(4995, $prices[$this->ca->id]->amount_minor);
        $this->assertSame(Currency::CAD, $prices[$this->ca->id]->currency);
        $this->assertSame(25000, $prices[$this->cm->id]->amount_minor);
        $this->assertSame(Currency::XAF, $prices[$this->cm->id]->currency);
        $this->assertTrue($product->collections->first()->is($collection));
        $this->assertTrue(Product::availableIn($this->ca)->whereKey($product)->exists());
        $this->assertFalse(Product::availableIn($this->cm)->whereKey($product)->exists());
    }

    public function test_draft_inactive_category_and_inactive_market_hide_products(): void
    {
        $product = $this->save($this->data(['markets' => [['market_id' => $this->ca->id, 'is_available' => true]]]));
        $this->assertFalse(Product::availableIn($this->ca)->exists());
        $product->update(['status' => 'published']);
        $this->assertTrue(Product::availableIn($this->ca)->exists());
        $this->ca->update(['is_active' => false]);
        $this->assertFalse(Product::availableIn($this->ca)->exists());
        $this->ca->update(['is_active' => true]);
        $this->category->update(['is_active' => false]);
        $this->assertFalse(Product::availableIn($this->ca)->exists());
        $this->category->update(['is_active' => true]);
        app(TaxonomyService::class)->archive($this->manager, $this->category);
        $this->assertFalse(Product::availableIn($this->ca)->exists());
        $this->assertSame($this->category->id, $product->fresh()->category->id);
    }

    #[DataProvider('badAmounts')]
    public function test_invalid_prices_are_rejected_without_partial_writes(string $amount, string $country): void
    {
        $data = $this->data();
        $data['variants'][0]['prices'] = [['market_id' => $country === 'CA' ? $this->ca->id : $this->cm->id, 'amount' => $amount]];
        $this->invalid(fn () => $this->save($data), 'variants.0.prices.0.amount');
        $this->assertDatabaseCount('products', 0);
        $this->assertDatabaseCount('product_variants', 0);
    }

    public static function badAmounts(): array
    {
        return [['-1', 'CA'], ['4.999', 'CA'], ['1e3', 'CA'], ['NaN', 'CA'], ['10.5', 'CM'], ['10.00', 'CM'], ['10000000000', 'CA'], ['1000000000000', 'CM']];
    }

    public function test_each_enabled_market_requires_a_price_for_each_active_variant(): void
    {
        $data = $this->data(['markets' => [['market_id' => $this->cm->id, 'is_available' => true]]]);
        $data['variants'][0]['prices'] = [['market_id' => $this->ca->id, 'amount' => '20']];
        $this->invalid(fn () => $this->save($data), 'variants.0.prices');
        $this->assertDatabaseCount('products', 0);
    }

    public function test_at_least_one_active_variant_is_required(): void
    {
        $this->invalid(fn () => $this->save($this->data(['variants' => []])), 'variants');
        $data = $this->data();
        $data['variants'][0]['is_active'] = false;
        $this->invalid(fn () => $this->save($data), 'variants');
    }

    public function test_duplicate_skus_and_duplicate_market_prices_are_rejected(): void
    {
        $this->save($this->data());
        $this->invalid(fn () => $this->save($this->data(['slug' => 'second'])), 'variants.0.sku');
        $data = $this->data(['slug' => 'second']);
        $data['variants'][0]['sku'] = 'SECOND';
        $data['variants'][0]['prices'][] = $data['variants'][0]['prices'][0];
        $this->invalid(fn () => $this->save($data), 'variants.0.prices');
        $this->assertDatabaseCount('products', 1);
    }

    public function test_duplicate_sku_within_product_is_rejected(): void
    {
        $data = $this->data();
        $data['variants'][] = $data['variants'][0];
        $this->invalid(fn () => $this->save($data), 'variants.0.sku');
    }

    public function test_currency_cannot_be_supplied_or_swapped_by_client(): void
    {
        $data = $this->data();
        $data['variants'][0]['prices'][0]['currency'] = 'XAF';
        $this->invalid(fn () => $this->save($data), 'variants.0.prices.0');
    }

    public function test_updates_preserve_ids_and_reject_stale_retries(): void
    {
        $product = $this->save($this->data());
        $data = app(CatalogService::class)->formData($product);
        $variantId = $product->variants->first()->id;
        $data['name'] = 'Nouveau nom';
        $updated = app(CatalogService::class)->save($this->manager, $data, $product);
        $this->assertSame($variantId, $updated->variants->first()->id);
        $this->assertSame(1, $updated->version);
        $this->invalid(fn () => app(CatalogService::class)->save($this->manager, $data, $product), 'version');
        $this->assertDatabaseCount('product_variants', 1);
        $this->assertDatabaseCount('market_prices', 2);
    }

    public function test_removed_variants_are_archived_and_skus_remain_reserved(): void
    {
        $product = $this->save($this->data());
        $old = $product->variants->first();
        $data = app(CatalogService::class)->formData($product);
        $data['variants'] = [['sku' => 'NEW', 'label' => 'Nouveau', 'is_active' => true, 'prices' => []]];
        $updated = app(CatalogService::class)->save($this->manager, $data, $product);
        $this->assertSoftDeleted($old);
        $this->assertFalse($old->fresh()->is_active);
        $this->assertCount(1, $updated->variants);
        $this->assertDatabaseCount('market_prices', 2);
        $this->invalid(fn () => $this->save($this->data(['slug' => 'second'])), 'variants.0.sku');
    }

    public function test_variant_id_from_another_product_cannot_be_modified(): void
    {
        $one = $this->save($this->data());
        $other = Product::factory()->create();
        $data = app(CatalogService::class)->formData($one);
        $data['variants'][0]['id'] = $other->variants->first()->id;
        $this->invalid(fn () => app(CatalogService::class)->save($this->manager, $data, $one), 'variants.0.id');
        $this->assertSame(0, $one->fresh()->version);
    }

    public function test_staff_can_read_but_cannot_write_catalog(): void
    {
        $staff = User::factory()->create();
        $staff->assignRole(StaffRole::Staff);
        $product = $this->save($this->data());
        $this->actingAs($staff)->get(ProductResource::getUrl())->assertOk();
        $this->get(ProductResource::getUrl('view', ['record' => $product]))->assertOk();
        $this->get(ProductResource::getUrl('create'))->assertForbidden();
        $this->get(ProductResource::getUrl('edit', ['record' => $product]))->assertForbidden();
        $this->get(CategoryResource::getUrl('create'))->assertForbidden();
        $this->expectException(AuthorizationException::class);
        app(CatalogService::class)->save($staff, $this->data());
    }

    public function test_unprivileged_account_and_guests_cannot_read_catalog(): void
    {
        $this->actingAs(User::factory()->create())->get(ProductResource::getUrl())->assertForbidden();
        auth()->logout();
        $this->get(ProductResource::getUrl())->assertRedirect('/admin/login');
    }

    public function test_filament_can_create_edit_filter_and_archive_product(): void
    {
        Livewire::test(CreateProduct::class)->fillForm($this->data())->call('create')->assertHasNoFormErrors();
        $product = Product::firstOrFail();
        Livewire::test(ListProducts::class)->assertCanSeeTableRecords([$product])
            ->filterTable('status', 'published')->assertCanNotSeeTableRecords([$product]);
        $page = Livewire::test(EditProduct::class, ['record' => $product->id]);
        $page->fillForm(['name' => 'Modifié'])->call('save')->assertHasNoFormErrors();
        // A second save must use the refreshed version and existing variant IDs.
        $page->fillForm(['name' => 'Encore modifié'])->call('save')->assertHasNoFormErrors();
        $this->assertDatabaseCount('product_variants', 1);
        $this->assertSame('Encore modifié', $product->fresh()->name);
        $page->callAction('delete');
        $this->assertSoftDeleted($product);
        $this->assertDatabaseCount('market_prices', 2);
    }

    public function test_filament_displays_service_validation_errors(): void
    {
        $data = $this->data();
        $data['variants'][0]['prices'][0]['amount'] = '-10';
        $page = Livewire::test(CreateProduct::class)->fillForm($data)->call('create');
        $variantKey = array_key_first($page->get('data.variants'));
        $priceKey = array_key_first($page->get('data.variants')[$variantKey]['prices']);
        $page->assertHasFormErrors(["variants.$variantKey.prices.$priceKey.amount"]);
        $this->assertDatabaseCount('products', 0);
    }

    public function test_taxonomy_crud_and_repeated_seeding_preserve_archived_records(): void
    {
        Livewire::test(CreateCategory::class)->fillForm(['name' => 'Sacs', 'slug' => 'sacs', 'is_active' => true])->call('create')->assertHasNoFormErrors();
        $category = Category::where('slug', 'sacs')->firstOrFail();
        Livewire::test(EditCategory::class, ['record' => $category->id])->fillForm(['name' => 'Sacs à main'])->call('save')->assertHasNoFormErrors()->callAction('delete');
        $this->assertSoftDeleted($category);
        Livewire::test(CreateCulturalCollection::class)->fillForm(['name' => 'Collection test', 'slug' => 'collection-test', 'is_active' => true])->call('create')->assertHasNoFormErrors();
        $this->get(CulturalCollectionResource::getUrl())->assertOk();
        $this->category->delete();
        $this->seed(DatabaseSeeder::class);
        $this->assertDatabaseCount('categories', 4);
        $this->assertSoftDeleted($this->category);
        $this->invalid(fn () => app(TaxonomyService::class)->save($this->manager, Category::class, ['name' => '', 'slug' => 'Bad Slug', 'is_active' => true]), 'name');
    }

    public function test_image_upload_stores_alt_text_and_random_name_and_can_be_removed(): void
    {
        Storage::fake('public');
        $product = $this->save($this->data());
        $media = app(ProductMediaService::class)->add($this->manager, $product, UploadedFile::fake()->image('photo.jpg'), 'Vue du vêtement');
        $this->assertSame('Vue du vêtement', $media->getCustomProperty('alt'));
        $this->assertNotSame('photo.jpg', $media->file_name);
        Storage::disk('public')->assertExists($media->getPathRelativeToRoot());
        $this->assertTrue(app(ProductMediaService::class)->remove($this->manager, $product, $media->id));
        Storage::disk('public')->assertMissing($media->getPathRelativeToRoot());
    }

    public function test_filament_image_action_and_archiving_preserve_media(): void
    {
        Storage::fake('public');
        $product = $this->save($this->data());
        Livewire::test(EditProduct::class, ['record' => $product->id])
            ->callAction('addImage', data: ['image' => UploadedFile::fake()->image('mode.png'), 'alt' => 'Vue de face'])
            ->assertHasNoActionErrors();
        $media = $product->fresh()->getFirstMedia('images');
        $this->assertNotNull($media);
        app(CatalogService::class)->archive($this->manager, $product);
        $this->assertSoftDeleted($product);
        $this->assertDatabaseHas('media', ['id' => $media->id]);
        Storage::disk('public')->assertExists($media->getPathRelativeToRoot());
    }

    public function test_saving_again_does_not_duplicate_publication_or_prices(): void
    {
        $product = $this->save($this->data(['markets' => [['market_id' => $this->ca->id, 'is_available' => true]]]));
        $data = app(CatalogService::class)->formData($product);
        app(CatalogService::class)->save($this->manager, $data, $product);
        $this->assertDatabaseCount('market_products', 1);
        $this->assertDatabaseCount('market_prices', 2);
        $data = app(CatalogService::class)->formData($product->fresh());
        $data['markets'] = [];
        app(CatalogService::class)->save($this->manager, $data, $product);
        $this->assertDatabaseHas('market_products', ['product_id' => $product->id, 'is_available' => false]);
    }

    public function test_media_rejects_executable_uploads_and_wrong_product_ownership(): void
    {
        Storage::fake('public');
        $product = $this->save($this->data());
        $service = app(ProductMediaService::class);
        $this->invalid(fn () => $service->add($this->manager, $product, UploadedFile::fake()->create('shell.php', 10, 'text/plain'), 'Image'), 'image');
        $media = $service->add($this->manager, $product, UploadedFile::fake()->image('photo.png'), 'Image');
        $other = Product::factory()->create();
        try {
            $service->remove($this->manager, $other, $media->id);
            $this->fail('Media ownership must be checked');
        } catch (ModelNotFoundException) {
            $this->assertDatabaseHas('media', ['id' => $media->id]);
        }
    }

    public function test_staff_cannot_upload_images_or_archive_taxonomy(): void
    {
        $staff = User::factory()->create();
        $staff->assignRole(StaffRole::Staff);
        $product = $this->save($this->data());
        try {
            app(TaxonomyService::class)->archive($staff, $this->category);
            $this->fail('Staff cannot archive');
        } catch (AuthorizationException) {
            $this->assertNotSoftDeleted($this->category);
        }
        $this->expectException(AuthorizationException::class);
        app(ProductMediaService::class)->add($staff, $product, UploadedFile::fake()->image('x.jpg'), 'Image');
    }

    public function test_database_enforces_unique_market_variant_prices(): void
    {
        $product = $this->save($this->data());
        $price = $product->variants->first()->prices->first();
        $this->expectException(QueryException::class);
        MarketPrice::create($price->only(['market_id', 'product_variant_id', 'amount_minor', 'currency']));
    }

    public function test_mysql_enforces_nonnegative_prices(): void
    {
        if (DB::connection()->getDriverName() !== 'mysql') {
            $this->markTestSkipped('MySQL unsigned constraint is verified by tools/verify-mysql.php.');
        }
        $product = $this->save($this->data());
        $this->expectException(QueryException::class);
        $product->variants->first()->prices->first()->update(['amount_minor' => -1]);
    }

    private function data(array $overrides = []): array
    {
        return array_replace([
            'name' => 'Vêtement test', 'slug' => 'vetement-test', 'category_id' => $this->category->id,
            'status' => 'draft', 'collections' => [], 'markets' => [],
            'variants' => [[
                'sku' => 'TEST-001', 'label' => 'Standard', 'is_active' => true,
                'prices' => [['market_id' => $this->ca->id, 'amount' => '49,95'], ['market_id' => $this->cm->id, 'amount' => '25000']],
            ]],
        ], $overrides);
    }

    private function save(array $data): Product
    {
        return app(CatalogService::class)->save($this->manager, $data);
    }

    private function invalid(callable $callback, string $field): void
    {
        try {
            $callback();
            $this->fail('Expected validation failure for '.$field);
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey($field, $exception->errors());
        }
    }
}
