<?php

namespace Tests\Feature;

use App\Services\SocialLinks;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class SocialLinksTest extends TestCase
{
    use RefreshDatabase;

    public function test_facebook_link_is_shared_without_enabling_unconfigured_networks(): void
    {
        $this->withoutVite();
        $this->seed(DatabaseSeeder::class);
        $url = 'https://www.facebook.com/profile.php?id=100071095101171';
        config(['communications.social_facebook' => $url, 'communications.social_tiktok' => null, 'communications.social_youtube' => null]);
        $this->get('/')->assertOk()->assertInertia(fn (Assert $page) => $page->has('shop.social_links', 1)
            ->where('shop.social_links.0.label', 'Facebook')->where('shop.social_links.0.url', $url));
    }

    public function test_three_networks_accept_valid_https_profiles_and_reject_unsafe_links(): void
    {
        config(['communications.social_facebook' => 'https://www.facebook.com/profile.php?id=123',
            'communications.social_tiktok' => 'https://www.tiktok.com/@compte-test', 'communications.social_youtube' => 'https://www.youtube.com/@compte-test']);
        $this->assertCount(3, app(SocialLinks::class)->publicLinks());
        foreach (['javascript:alert(1)', 'http://www.facebook.com/test', 'https://facebook.com.evil.invalid/test', 'https://attacker@www.facebook.com/test', 'https://www.facebook.com:444/test', 'https://www.youtube.com/test'] as $bad) {
            config(['communications.social_facebook' => $bad]);
            $this->assertSame(['tiktok', 'youtube'], array_column(app(SocialLinks::class)->publicLinks(), 'network'));
        }
    }
}
