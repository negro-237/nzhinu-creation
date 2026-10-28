<?php

namespace Tests\Feature;

use Database\Seeders\MarketsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    use RefreshDatabase;

    /**
     * A basic test example.
     */
    public function test_the_application_returns_a_successful_response(): void
    {
        $this->withoutVite();
        $this->seed(MarketsSeeder::class);
        $response = $this->get('/');

        $response->assertStatus(200);
        $response->assertInertia(fn (Assert $page) => $page->component('Welcome')
            ->has('markets', 2)->where('markets.0.code', 'CA')->where('markets.1.currency', 'XAF')
            ->where('locale', 'fr')->where('content.markets', 'Retrouvez-nous en boutique'));
    }
}
