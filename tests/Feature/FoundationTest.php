<?php

namespace Tests\Feature;

use App\Enums\Currency;
use App\Enums\StaffPermission;
use App\Enums\StaffRole;
use App\Models\Market;
use App\Models\StockLocation;
use App\Models\User;
use App\Services\StaffService;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\StaffRolesSeeder;
use Filament\Auth\Pages\Login;
use Filament\Facades\Filament;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class FoundationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        if (getenv('VERIFY_MYSQL') === '1') {
            $this->assertSame('mysql', DB::connection()->getDriverName());
            $this->assertStringStartsWith('nzhinu_verify_', DB::connection()->getDatabaseName());
        }
        $this->seed(StaffRolesSeeder::class);
        Filament::setCurrentPanel(Filament::getPanel('admin'));
    }

    public function test_seeders_are_repeatable_and_preserve_edited_and_archived_locations(): void
    {
        $this->seed(DatabaseSeeder::class);
        $location = StockLocation::firstOrFail();
        $location->update(['address' => 'Adresse réelle validée', 'is_placeholder' => false]);
        $location->delete();
        $this->seed(DatabaseSeeder::class);

        $this->assertDatabaseCount('markets', 2);
        $this->assertDatabaseCount('stock_locations', 2);
        $this->assertDatabaseCount('users', 0);
        $this->assertDatabaseCount('roles', 3);
        $this->assertDatabaseCount('permissions', count(StaffPermission::cases()));
        $this->assertSame('Adresse réelle validée', $location->fresh()->address);
        $this->assertSoftDeleted($location);
        $this->assertFalse(StockLocation::where('code', 'CM-EXAMPLE')->firstOrFail()->is_active);
        $this->assertSame(2, Market::where('code', 'CA')->firstOrFail()->currency->fractionDigits());
        $this->assertSame(0, Market::where('code', 'CM')->firstOrFail()->currency->fractionDigits());
    }

    public function test_guests_are_redirected_and_public_registration_is_unavailable(): void
    {
        $this->get('/admin')->assertRedirect('/admin/login');
        $this->get('/admin/login')->assertOk();
        $this->get('/register')->assertNotFound();
        $this->get('/admin/register')->assertNotFound();
    }

    public function test_account_without_permission_cannot_access_panel_even_locally(): void
    {
        $this->actingAs(User::factory()->create())->get('/admin')->assertForbidden();
    }

    #[DataProvider('roles')]
    public function test_staff_roles_can_access_panel_with_limited_permissions(string $role, bool $manageStaff, bool $manageMarkets): void
    {
        $user = User::factory()->create();
        $user->assignRole($role);
        $this->actingAs($user)->get('/admin')->assertOk();
        $this->assertSame($manageStaff, $user->can(StaffPermission::ManageStaff->value));
        $this->assertSame($manageMarkets, Gate::forUser($user)->allows('viewAny', Market::class));
        $this->assertSame($manageMarkets, Gate::forUser($user)->allows('viewAny', StockLocation::class));
    }

    public static function roles(): array
    {
        return [['administrator', true, true], ['manager', false, true], ['staff', false, false]];
    }

    public function test_staff_can_log_in_and_log_out(): void
    {
        $user = User::factory()->create();
        $user->assignRole(StaffRole::Staff);
        Livewire::test(Login::class)->fillForm(['email' => $user->email, 'password' => 'password'])
            ->call('authenticate')->assertHasNoFormErrors()->assertRedirect('/admin');
        $this->assertAuthenticatedAs($user);
        $this->post('/admin/logout')->assertRedirect('/admin/login');
        $this->assertGuest();
    }

    public function test_wrong_password_and_non_staff_login_are_rejected(): void
    {
        $user = User::factory()->create();
        Livewire::test(Login::class)->fillForm(['email' => $user->email, 'password' => 'wrong'])
            ->call('authenticate')->assertHasFormErrors(['email']);
        $this->assertGuest();
        Livewire::test(Login::class)->fillForm(['email' => $user->email, 'password' => 'password'])
            ->call('authenticate')->assertHasFormErrors(['email']);
        $this->assertGuest();
    }

    public function test_authorized_staff_creation_hashes_password_and_assigns_role(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole(StaffRole::Administrator);
        $user = app(StaffService::class)->create($admin, $this->staffData());
        $this->assertTrue($user->hasRole(StaffRole::Staff));
        $this->assertTrue(Hash::check('Strong-Test-Password42!', $user->password));
    }

    public function test_manager_cannot_create_staff(): void
    {
        $manager = User::factory()->create();
        $manager->assignRole(StaffRole::Manager);
        $this->expectException(AuthorizationException::class);
        app(StaffService::class)->create($manager, $this->staffData());
    }

    public function test_invalid_staff_data_is_rejected_without_creating_account(): void
    {
        try {
            app(StaffService::class)->createForServerOperator(['name' => '', 'email' => 'invalid', 'password' => 'short', 'role' => 'owner']);
            $this->fail('Validation should fail');
        } catch (ValidationException $exception) {
            $this->assertCount(4, $exception->errors());
            $this->assertDatabaseCount('users', 0);
        }
    }

    public function test_duplicate_staff_creation_does_not_change_existing_account(): void
    {
        app(StaffService::class)->createForServerOperator($this->staffData());
        try {
            app(StaffService::class)->createForServerOperator($this->staffData());
            $this->fail('Duplicate email should fail');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('email', $exception->errors());
            $this->assertDatabaseCount('users', 1);
        }
    }

    public function test_cli_creates_staff_without_password_arguments(): void
    {
        $this->artisan('staff:create')->expectsQuestion('Nom', 'Équipe test')
            ->expectsQuestion('Adresse e-mail', 'staff@example.com')
            ->expectsQuestion('Mot de passe (12 caractères minimum, majuscule, minuscule, chiffre et symbole)', 'Strong-Test-Password42!')
            ->expectsChoice('Rôle', 'staff', array_column(StaffRole::cases(), 'value'))
            ->assertSuccessful();
        $this->assertTrue(User::where('email', 'staff@example.com')->firstOrFail()->hasRole(StaffRole::Staff));
    }

    public function test_location_relations_and_unique_code_constraint(): void
    {
        $market = Market::factory()->create();
        $location = StockLocation::factory()->for($market)->create();
        $this->assertTrue($location->market->is($market));
        $this->assertTrue($market->stockLocations->first()->is($location));
        $this->assertSame(Currency::CAD, $market->currency);
        $this->expectException(QueryException::class);
        StockLocation::factory()->for($market)->create(['code' => $location->code]);
    }

    private function staffData(): array
    {
        return ['name' => 'Équipe test', 'email' => 'staff@example.com', 'password' => 'Strong-Test-Password42!', 'role' => 'staff'];
    }
}
