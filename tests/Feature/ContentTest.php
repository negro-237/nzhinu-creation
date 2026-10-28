<?php

namespace Tests\Feature;

use App\Enums\StaffRole;
use App\Filament\Resources\CmsPageResource;
use App\Filament\Resources\CmsPageResource\Pages\ManagePages;
use App\Filament\Resources\StoreBannerResource\Pages\ManageBanners;
use App\Models\CmsPage;
use App\Models\User;
use App\Services\ContentService;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;
use Inertia\Testing\AssertableInertia as Assert;
use Livewire\Livewire;
use Tests\Support\InventoryFixture;
use Tests\TestCase;

class ContentTest extends TestCase
{
    use InventoryFixture, RefreshDatabase;

    private array $f;

    private function pageData(array $extra = []): array
    {
        return $extra + ['title' => 'Notre maison', 'slug' => 'notre-maison', 'body' => "Première ligne\n<script>alert(1)</script>", 'meta_description' => 'Présentation test', 'is_published' => true];
    }

    private function bannerData(array $extra = []): array
    {
        return $extra + ['title' => 'Actualité test', 'body' => 'Message public', 'sort_order' => 0, 'is_published' => true, 'link_path' => '/boutique', 'link_label' => 'Découvrir'];
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        Notification::fake();
        $this->f = $this->fixture();
    }

    public function test_published_pages_are_scoped_by_market_and_drafts_and_archives_are_private(): void
    {
        $service = app(ContentService::class);
        $page = $service->savePage($this->f['actor'], $this->pageData(['market_id' => $this->f['ca']->id]));
        $this->get('/pages/notre-maison')->assertOk()->assertInertia(fn (Assert $p) => $p->component('ContentPage')->where('page.body', $page->body)->missing('page.version'));
        $this->get('/pages/notre-maison')->assertSee('Présentation test');
        $this->post('/marche', ['code' => 'CM']);
        $this->get('/pages/notre-maison')->assertNotFound();
        $this->post('/marche', ['code' => 'CA']);
        $draft = $service->savePage($this->f['actor'], $this->pageData(['slug' => 'brouillon', 'is_published' => false]));
        $this->get('/pages/brouillon')->assertNotFound();
        $service->archive($this->f['actor'], $page, 0);
        $this->get('/pages/notre-maison')->assertNotFound();
        $this->assertSoftDeleted($page);
        $this->expectException(ValidationException::class);
        $service->savePage($this->f['actor'], $this->pageData());
    }

    public function test_banners_respect_publication_schedule_sorting_and_market_without_external_links(): void
    {
        $service = app(ContentService::class);
        $service->saveBanner($this->f['actor'], $this->bannerData(['title' => 'Visible', 'market_id' => $this->f['ca']->id, 'starts_at' => now()->subHour()->toDateTimeString(), 'ends_at' => now()->addHour()->toDateTimeString()]));
        foreach ([['title' => 'Future', 'starts_at' => now()->addHour()->toDateTimeString()], ['title' => 'Passée', 'ends_at' => now()->subHour()->toDateTimeString()], ['title' => 'Autre marché', 'market_id' => $this->f['cm']->id], ['title' => 'Brouillon', 'is_published' => false]] as $extra) {
            $service->saveBanner($this->f['actor'], $this->bannerData($extra));
        }
        $this->get('/')->assertInertia(fn (Assert $p) => $p->has('banners', 1)->where('banners.0.title', 'Visible'));
        foreach (['https://example.com', '//example.com', 'javascript:alert(1)', '/pages/../admin', '/boutique?redirect=evil'] as $link) {
            try {
                $service->saveBanner($this->f['actor'], $this->bannerData(['link_path' => $link]));
                $this->fail('Unsafe link');
            } catch (ValidationException) {
                $this->assertTrue(true);
            }
        }
        $this->assertDatabaseCount('store_banners', 5);
    }

    public function test_stale_edits_invalid_schedule_and_staff_writes_are_rejected(): void
    {
        $service = app(ContentService::class);
        $page = $service->savePage($this->f['actor'], $this->pageData());
        $service->savePage($this->f['actor'], $this->pageData(['version' => 0, 'title' => 'Titre modifié']), $page->id);
        try {
            $service->savePage($this->f['actor'], $this->pageData(['version' => 0]), $page->id);
            $this->fail('Stale edit');
        } catch (ValidationException) {
            $this->assertSame('Titre modifié', $page->fresh()->title);
        }
        try {
            $service->saveBanner($this->f['actor'], $this->bannerData(['starts_at' => '2026-10-27', 'ends_at' => '2026-10-26']));
            $this->fail('Invalid schedule');
        } catch (ValidationException) {
            $this->assertDatabaseCount('store_banners', 0);
        }
        $staff = User::factory()->create();
        $staff->assignRole(StaffRole::Staff);
        $this->actingAs($staff)->get(CmsPageResource::getUrl())->assertForbidden();
        $this->expectException(AuthorizationException::class);
        $service->savePage($staff, $this->pageData(['slug' => 'interdit']));
    }

    public function test_filament_creates_updates_and_archives_content_through_services(): void
    {
        $this->actingAs($this->f['actor']);
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        Livewire::test(ManagePages::class)->callAction('create', data: $this->pageData())->assertHasNoActionErrors();
        $page = CmsPage::firstOrFail();
        Livewire::test(ManagePages::class)->callAction(TestAction::make('edit')->table($page), data: $this->pageData(['version' => 0, 'title' => 'Nouveau titre']))->assertHasNoActionErrors();
        $this->assertSame('Nouveau titre', $page->fresh()->title);
        Livewire::test(ManageBanners::class)->callAction('create', data: $this->bannerData())->assertHasNoActionErrors();
        $this->assertDatabaseCount('store_banners', 1);
        Livewire::test(ManagePages::class)->callAction(TestAction::make('archive')->table($page), data: ['version' => 1])->assertHasNoActionErrors();
        $this->assertSoftDeleted($page);
    }
}
