<?php

namespace Tests\Feature;

use App\Enums\StaffRole;
use App\Filament\Widgets\OperationsOverview;
use App\Models\User;
use App\Services\OperationsDashboard;
use App\Services\OrderAccess;
use App\Services\WhatsAppContact;
use Filament\Facades\Filament;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;
use Tests\Support\InventoryFixture;
use Tests\Support\OrderFixture;
use Tests\TestCase;

class OperationsSecurityTest extends TestCase
{
    use InventoryFixture, OrderFixture, RefreshDatabase;

    private array $f;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        Notification::fake();
        Queue::fake();
        $this->f = $this->fixture();
    }

    public function test_dashboard_keeps_market_currencies_separate_and_counts_financial_review(): void
    {
        $admin = $this->administrator();
        $order = $this->newOrder($this->f);
        $this->verifyPayment($admin, $this->receipt($this->f['actor'], $order, '110'));
        $cm = $this->newOrder($this->f);
        // Snapshot reporting fixture: two independent currencies, not a cross-border fulfillment.
        $cm->update(['market_id' => $this->f['cm']->id, 'currency' => 'XAF', 'verified_paid_minor' => 25000, 'verified_net_minor' => 25000]);
        $report = app(OperationsDashboard::class)->report($admin);
        $this->assertGreaterThan(0, $report['pending_events']);
        $this->assertCount(2, $report['markets']);
        $this->assertSame(['CAD', 'XAF'], array_map(fn ($row) => $row['currency']->value, $report['markets']));
        $this->assertSame(11000, $report['markets'][0]['net']);
        $this->assertSame(25000, $report['markets'][1]['net']);
        $this->assertSame(1, $report['markets'][0]['review']);
        $this->actingAs($admin);
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        Livewire::test(OperationsOverview::class)->assertSee('Canada')->assertSee('Cameroun')->assertSee('CAD')->assertSee('XAF');
        $staff = User::factory()->create();
        $staff->assignRole(StaffRole::Staff);
        $this->actingAs($staff);
        $this->assertFalse(OperationsOverview::canView());
        $this->expectException(AuthorizationException::class);
        app(OperationsDashboard::class)->report($staff);
    }

    public function test_security_headers_csp_nonce_private_pages_and_admin_cache(): void
    {
        $response = $this->get('/')->assertOk()->assertHeader('X-Content-Type-Options', 'nosniff')->assertHeader('X-Frame-Options', 'DENY');
        $csp = $response->headers->get('Content-Security-Policy');
        $this->assertStringContainsString("script-src 'self' 'nonce-", $csp);
        $this->assertStringNotContainsString('unsafe-eval', $csp);
        $this->assertStringContainsString("frame-ancestors 'none'", $csp);
        $second = $this->get('/')->headers->get('Content-Security-Policy');
        $this->assertNotSame($csp, $second);
        $this->get('/admin/login')->assertOk()->assertHeader('X-Robots-Tag', 'noindex, nofollow')->assertDontSee('fonts.bunny.net', false);
        $this->actingAs($this->f['actor'])->get('/admin')->assertOk()->assertDontSee('ui-avatars.com', false)->assertSee('data:image/svg+xml;base64,', false);
        auth()->forgetGuards();
        $order = $this->newOrder($this->f);
        $private = $this->get(app(OrderAccess::class)->link($order))->assertOk()->assertHeader('Referrer-Policy', 'no-referrer');
        $this->assertStringContainsString('no-store', $private->headers->get('Cache-Control'));
        $this->get('/commandes/'.$order->reference)->assertForbidden()->assertHeader('Referrer-Policy', 'no-referrer');
    }

    public function test_cart_rate_limit_is_enforced_without_creating_reservations(): void
    {
        for ($i = 0; $i < 60; $i++) {
            $this->delete('/panier')->assertRedirect();
        }
        $this->delete('/panier')->assertStatus(429);
        $this->assertDatabaseCount('inventory_reservations', 0);
    }

    public function test_whatsapp_is_manual_scoped_validated_and_does_not_expose_private_link_or_coordinates(): void
    {
        $service = app(WhatsAppContact::class);
        $this->assertNull($service->link($this->f['ca']));
        config(['communications.whatsapp_ca' => '15145550100', 'communications.whatsapp_cm' => 'javascript:alert(1)']);
        $order = $this->newOrder($this->f);
        $link = $service->link($this->f['ca'], $order);
        $this->assertStringStartsWith('https://wa.me/15145550100?text=', $link);
        $this->assertStringNotContainsString('signature', $link);
        $this->assertStringNotContainsString('15145550123', $link);
        $this->assertNull($service->link($this->f['cm']));
        $this->assertStringStartsWith('https://wa.me/15145550123?', $service->customerLink($this->f['actor'], $order));
        $order->update(['phone' => '5145550123']);
        $this->assertNull($service->customerLink($this->f['actor'], $order));
    }
}
