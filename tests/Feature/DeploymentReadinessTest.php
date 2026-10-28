<?php

namespace Tests\Feature;

use App\Services\CatalogService;
use App\Services\DeploymentReadiness;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Tests\Support\InventoryFixture;
use Tests\Support\OrderFixture;
use Tests\TestCase;

class DeploymentReadinessTest extends TestCase
{
    use InventoryFixture, OrderFixture, RefreshDatabase;

    public function test_preflight_flags_unsafe_settings_without_printing_secrets_or_changing_data(): void
    {
        Queue::fake();
        Notification::fake();
        $f = $this->fixture();
        $this->administrator();
        config(['app.debug' => true, 'app.url' => 'http://localhost', 'app.key' => 'PRIVATE-KEY-DO-NOT-PRINT',
            'session.secure' => false, 'session.encrypt' => false, 'communications.order_email_enabled' => true,
            'mail.default' => 'log', 'mail.from.address' => 'hello@example.com', 'communications.whatsapp_ca' => 'invalid']);
        $checks = collect(app(DeploymentReadiness::class)->checks())->keyBy('code');
        foreach (['environment', 'debug', 'https', 'session_secure', 'session_encrypted', 'mail', 'whatsapp_ca', 'catalog_CM'] as $code) {
            $this->assertFalse($checks[$code]['ok'], $code);
        }
        $this->assertTrue($checks['catalog_CA']['ok']);
        $this->assertFalse($checks['key']['ok']);
        $this->assertStringNotContainsString('PRIVATE-KEY', json_encode($checks));
        $this->artisan('boutique:preflight', ['--json' => true])->assertExitCode(1);
        $this->assertDatabaseCount('orders', 0);
        $this->assertDatabaseCount('users', 2);
        $this->assertSame(10, $f['inventory']->fresh()->quantity_on_hand);
        app(CatalogService::class)->archive($f['actor'], $f['product']);
        $afterArchive = collect(app(DeploymentReadiness::class)->checks())->keyBy('code');
        $this->assertFalse($afterArchive['catalog_CA']['ok']);
    }

    public function test_optional_email_can_be_disabled_and_valid_https_settings_are_accepted(): void
    {
        Queue::fake();
        $this->fixture();
        config(['app.url' => 'https://boutique.example.org', 'app.debug' => false, 'session.secure' => true, 'session.encrypt' => true,
            'communications.order_email_enabled' => false, 'communications.whatsapp_ca' => '', 'communications.whatsapp_cm' => '237699000000']);
        $checks = collect(app(DeploymentReadiness::class)->checks())->keyBy('code');
        foreach (['debug', 'https', 'session_secure', 'session_encrypted', 'mail', 'whatsapp_ca', 'whatsapp_cm'] as $code) {
            $this->assertTrue($checks[$code]['ok'], $code);
        }
        $this->assertFalse($checks['email_enabled']['blocking']);
    }
}
