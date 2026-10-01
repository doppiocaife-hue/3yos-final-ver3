<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class PrimaryAdminSetupTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config()->set('admin.primary_admin_setup_key', 'TestSetupKey-OnlyForFeatureTests#2026');
    }

    public function test_fresh_install_allows_first_time_setup(): void
    {
        $this->get(route('admin.setup'))
            ->assertOk()
            ->assertSee('Create Primary Administrator')
            ->assertSee('beneficiary')
            ->assertSee('keep the password private');

        $this->get(route('admin.login'))
            ->assertOk()
            ->assertSee(route('admin.setup'))
            ->assertSee('Primary Administrator Setup Required')
            ->assertSee('No Primary Administrator account has been set up yet. Please create the Primary Administrator account to continue.')
            ->assertDontSee('No active Primary Administrator is set up yet. The beneficiary can create the account for this system.');
    }

    public function test_beneficiary_can_create_and_then_log_in_as_the_database_primary_admin(): void
    {
        $response = $this->post(route('admin.setup.store'), [
            'setup_key' => $this->setupKey(),
            'name' => '  Beneficiary Admin  ',
            'email' => '  BENEFICIARY@example.test ',
            'password' => 'BeneficiarySecret#2026',
            'password_confirmation' => 'BeneficiarySecret#2026',
        ]);

        $response->assertRedirect(route('admin.login'));
        $response->assertSessionHas('success', 'Primary Administrator created successfully. Please sign in.');

        $admin = User::where('email', 'beneficiary@example.test')->firstOrFail();
        $this->assertSame('Beneficiary Admin', $admin->name);
        $this->assertSame('full', $admin->role);
        $this->assertTrue($admin->is_active);
        $this->assertTrue(Hash::check('BeneficiarySecret#2026', $admin->password));
        $this->assertDatabaseHas('activity_logs', [
            'user_id' => $admin->id,
            'action' => 'Primary Administrator account created',
        ]);
        $this->assertDatabaseMissing('activity_logs', ['description' => 'BeneficiarySecret#2026']);

        $this->post(route('admin.login.post'), [
            'email' => $admin->email,
            'password' => 'BeneficiarySecret#2026',
        ])->assertRedirect(route('admin.dashboard'));
        $this->assertSame($admin->id, session('admin_user_id'));
    }

    public function test_setup_is_locked_after_an_active_primary_admin_exists(): void
    {
        User::factory()->create(['role' => 'full', 'is_active' => true]);

        $this->get(route('admin.setup'))
            ->assertOk()
            ->assertSee('Primary Administrator setup has already been completed.')
            ->assertSee('Go to Admin Login')
            ->assertDontSee('name="password"', false);

        $this->post(route('admin.setup.store'), [
            'setup_key' => $this->setupKey(),
            'name' => 'Unexpected Admin',
            'email' => 'unexpected@example.test',
            'password' => 'AnotherSecret#2026',
            'password_confirmation' => 'AnotherSecret#2026',
        ])->assertRedirect(route('admin.setup'))
            ->assertSessionHas('error', 'Primary Administrator setup has already been completed.');

        $this->assertDatabaseMissing('users', ['email' => 'unexpected@example.test']);
    }

    public function test_setup_stays_locked_after_a_primary_account_is_disabled(): void
    {
        User::factory()->create(['role' => 'full', 'is_active' => false]);

        $this->get(route('admin.setup'))
            ->assertOk()
            ->assertSee('Primary Administrator setup has already been completed.')
            ->assertDontSee('name="password"', false);
    }

    public function test_setup_validates_name_email_password_and_confirmation(): void
    {
        $this->from(route('admin.setup'))
            ->post(route('admin.setup.store'), [
                'setup_key' => $this->setupKey(),
                'name' => '   ',
                'email' => 'not-an-email',
                'password' => 'weak',
                'password_confirmation' => 'different',
            ])
            ->assertRedirect(route('admin.setup'))
            ->assertSessionHasErrors(['name', 'email', 'password']);
    }

    public function test_setup_rejects_case_insensitive_duplicate_email(): void
    {
        User::factory()->create(['role' => 'limited', 'email' => 'Existing.Admin@example.test']);

        $this->from(route('admin.setup'))
            ->post(route('admin.setup.store'), [
                'setup_key' => $this->setupKey(),
                'name' => 'Beneficiary Admin',
                'email' => 'existing.admin@example.test',
                'password' => 'BeneficiarySecret#2026',
                'password_confirmation' => 'BeneficiarySecret#2026',
            ])
            ->assertRedirect(route('admin.setup'))
            ->assertSessionHasErrors('email');
    }

    public function test_setup_requires_the_configured_one_time_key(): void
    {
        $this->get(route('admin.setup'))->assertSee('One-time Setup Key');

        $this->post(route('admin.setup.store'), [
            'setup_key' => 'incorrect-key',
            'name' => 'Untrusted Admin',
            'email' => 'untrusted@example.test',
            'password' => 'AnotherSecret#2026',
            'password_confirmation' => 'AnotherSecret#2026',
        ])->assertRedirect(route('admin.setup'))
            ->assertSessionHas('error', 'The setup key is invalid.');

        $this->assertDatabaseMissing('users', ['email' => 'untrusted@example.test']);
    }

    public function test_setup_form_is_unavailable_without_a_configured_key(): void
    {
        config()->set('admin.primary_admin_setup_key', null);

        $this->get(route('admin.setup'))
            ->assertOk()
            ->assertSee('Primary Administrator setup is not available.')
            ->assertDontSee('name="setup_key"', false);

        $this->post(route('admin.setup.store'), [
            'name' => 'Unconfigured Admin',
            'email' => 'unconfigured@example.test',
            'password' => 'AnotherSecret#2026',
            'password_confirmation' => 'AnotherSecret#2026',
        ])->assertRedirect(route('admin.setup'))
            ->assertSessionHas('error', 'Primary Administrator setup is not available. Contact the system administrator.');

        $this->assertDatabaseMissing('users', ['email' => 'unconfigured@example.test']);
    }

    private function setupKey(): string
    {
        return 'TestSetupKey-OnlyForFeatureTests#2026';
    }
}
