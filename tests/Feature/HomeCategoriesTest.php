<?php

namespace Tests\Feature;

use App\Enums\StaffRole;
use App\Filament\Resources\CategoryResource\Pages\CreateCategory;
use App\Filament\Resources\CategoryResource\Pages\EditCategory;
use App\Models\Category;
use App\Models\Market;
use App\Models\Product;
use App\Models\User;
use App\Services\StorefrontCatalog;
use App\Services\TaxonomyService;
use Database\Seeders\CatalogTaxonomySeeder;
use Database\Seeders\DatabaseSeeder;
use Filament\Facades\Filament;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Inertia\Testing\AssertableInertia as Assert;
use Livewire\Livewire;
use Tests\TestCase;

class HomeCategoriesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->seed(DatabaseSeeder::class);
    }

    private function publish(Category $category, string $code = 'CM'): Product
    {
        return Product::factory()->for($category)->published()->withMarketPrices([$code => 20000])->create();
    }

    public function test_home_lists_only_active_categories_with_products_eligible_in_the_current_market(): void
    {
        foreach (Category::all() as $category) {
            $this->publish($category);
        }
        $optional = Category::factory()->create(['name' => 'Sans description', 'description' => null, 'icon' => null]);
        $this->publish($optional);
        $this->publish(Category::factory()->create(['is_active' => false]));
        $archived = Category::factory()->create();
        $this->publish($archived);
        $archived->delete();
        $this->publish(Category::factory()->create(), 'CA');
        $draftCategory = Category::factory()->create();
        $draft = $this->publish($draftCategory);
        $draft->update(['status' => 'draft']);
        $archivedProduct = $this->publish(Category::factory()->create());
        $archivedProduct->delete();
        $this->withSession(['shop.market' => 'CM'])->get('/')->assertOk()->assertInertia(fn (Assert $page) => $page->has('categories', 4));
        $rows = collect(app(StorefrontCatalog::class)->homeCategories(Market::where('code', 'CM')->firstOrFail()))->keyBy('slug');
        $this->assertSame('clothing', $rows['vetements']['icon']);
        $this->assertSame('bag', $rows['accessoires']['icon']);
        $this->assertSame('jewelry', $rows['bijoux']['icon']);
        $this->assertNull($rows[$optional->slug]['description']);
        $this->assertSame('grid', $rows[$optional->slug]['icon']);
        $this->assertSame(['name', 'slug', 'description', 'icon'], array_keys($rows['vetements']));
        $this->assertDatabaseCount('inventories', 0);
    }

    public function test_seed_enriches_legacy_categories_once_and_preserves_staff_edits_and_archival(): void
    {
        $clothing = Category::where('slug', 'vetements')->firstOrFail();
        $clothing->update(['icon' => null, 'description' => null]);
        $accessories = Category::where('slug', 'accessoires')->firstOrFail();
        $accessories->update(['icon' => null, 'description' => 'Texte du personnel', 'name' => 'Mes accessoires']);
        $jewelry = Category::where('slug', 'bijoux')->firstOrFail();
        $jewelry->delete();
        $this->seed(CatalogTaxonomySeeder::class);
        $this->assertSame('clothing', $clothing->fresh()->icon);
        $this->assertNotEmpty($clothing->fresh()->description);
        $this->assertSame('Texte du personnel', $accessories->fresh()->description);
        $this->assertSame('Mes accessoires', $accessories->fresh()->name);
        $clothing->refresh()->update(['description' => null, 'icon' => 'grid']);
        $this->seed(CatalogTaxonomySeeder::class);
        $this->assertNull($clothing->fresh()->description);
        $this->assertSame('grid', $clothing->fresh()->icon);
        $this->assertSoftDeleted($jewelry);
        $this->assertDatabaseCount('categories', 3);
    }

    public function test_category_service_validates_icon_choices_and_accepts_an_optional_description(): void
    {
        $manager = User::factory()->create()->assignRole(StaffRole::Manager);
        $data = ['name' => 'Nouvelle catégorie', 'slug' => 'nouvelle-categorie', 'is_active' => true, 'description' => null, 'icon' => 'bag'];
        $category = app(TaxonomyService::class)->save($manager, Category::class, $data);
        $this->assertSame('bag', $category->icon);
        $this->assertNull($category->description);
        try {
            app(TaxonomyService::class)->save($manager, Category::class, [...$data, 'icon' => '<svg onload=alert(1)>'], $category);
            $this->fail('Only known icon keys may be saved');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('icon', $exception->errors());
            $this->assertSame('bag', $category->fresh()->icon);
        }
    }

    public function test_staff_without_catalog_permissions_cannot_change_category_presentation(): void
    {
        $staff = User::factory()->create()->assignRole(StaffRole::Staff);
        $this->expectException(AuthorizationException::class);
        app(TaxonomyService::class)->save($staff, Category::class, ['name' => 'Interdit', 'slug' => 'interdit', 'is_active' => true, 'icon' => 'grid']);
    }

    public function test_staff_can_configure_the_icon_and_optional_description_in_filament(): void
    {
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $this->actingAs(User::factory()->create()->assignRole(StaffRole::Manager));
        Livewire::test(CreateCategory::class)->fillForm(['name' => 'Sacs illustrés', 'slug' => 'sacs-illustres', 'icon' => 'bag',
            'description' => 'Une description de catégorie', 'is_active' => true])->call('create')->assertHasNoFormErrors();
        $category = Category::where('slug', 'sacs-illustres')->firstOrFail();
        $this->assertSame('bag', $category->icon);
        $editor = Livewire::test(EditCategory::class, ['record' => $category->id])->assertFormSet(['icon' => 'bag']);
        $editor->fillForm(['description' => null])->call('save')->assertHasNoFormErrors()->assertFormSet(['icon' => 'bag']);
        $this->assertSame('bag', $category->fresh()->icon);
        $editor->fillForm(['icon' => 'grid'])->call('save')->assertHasNoFormErrors()->assertFormSet(['icon' => 'grid']);
        $this->assertSame('grid', $category->fresh()->icon);
        $this->assertNull($category->fresh()->description);
    }

    public function test_empty_categories_are_not_promoted_on_the_homepage(): void
    {
        $this->get('/')->assertOk()->assertInertia(fn (Assert $page) => $page->has('categories', 0));
    }
}
