<?php

namespace Tests\Feature;

use App\Models\ActivityLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AdminAccountManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_existing_and_new_admins_are_active_by_default(): void
    {
        $actor = User::factory()->create(['role' => 'full']);
        $existing = User::factory()->create(['role' => 'limited']);
        $createdAdmin = User::factory()->make(['role' => 'full']);

        $this->assertTrue($existing->is_active);

        $response = $this->withSession($this->fullAdminSession($actor))
            ->post(route('admin.users.store'), [
                'name' => 'New Primary Admin',
                'email' => $createdAdmin->email,
                'password' => 'StrongPassword#2026',
                'password_confirmation' => 'StrongPassword#2026',
                'role' => 'full',
            ]);

        $response->assertRedirect()->assertSessionHasNoErrors();
        $this->assertDatabaseHas('users', [
            'email' => $createdAdmin->email,
            'role' => 'full',
            'is_active' => true,
        ]);
    }

    public function test_team_admin_page_shows_account_status_and_management_actions(): void
    {
        $actor = User::factory()->create(['role' => 'full']);
        $activeAdmin = User::factory()->create(['role' => 'full', 'is_active' => true]);
        $disabledAdmin = User::factory()->create(['role' => 'limited', 'is_active' => false]);

        $this->withSession($this->fullAdminSession($actor))
            ->get(route('admin.users'))
            ->assertOk()
            ->assertSee('Active')
            ->assertSee('Disabled')
            ->assertSee('Edit name')
            ->assertSee('Enable')
            ->assertSee('Disable')
            ->assertSee($activeAdmin->name)
            ->assertSee($disabledAdmin->name);
    }

    public function test_full_admin_can_disable_and_enable_an_account_without_deleting_it_or_its_history(): void
    {
        $actor = User::factory()->create(['role' => 'full']);
        $target = User::factory()->create(['role' => 'limited']);
        $history = ActivityLog::create([
            'user_id' => $target->id,
            'actor_name' => $target->name,
            'actor_email' => $target->email,
            'actor_role' => 'limited',
            'action' => 'Previous action',
            'activity_date' => now()->toDateString(),
            'activity_time' => now()->toTimeString(),
            'description' => 'Historical activity.',
        ]);
        $originalSessionVersion = $target->session_version;

        $this->withSession($this->fullAdminSession($actor))
            ->patch(route('admin.users.status', $target), ['is_active' => '0', 'current_admin_password' => 'password'])
            ->assertRedirect();

        $this->assertDatabaseHas('users', ['id' => $target->id, 'is_active' => false]);
        $this->assertSame($originalSessionVersion + 1, $target->fresh()->session_version);
        $this->assertDatabaseHas('activity_logs', ['id' => $history->id, 'user_id' => $target->id]);
        $this->assertDatabaseHas('activity_logs', [
            'user_id' => $actor->id,
            'action' => 'Disabled administrator',
            'description' => "{$actor->name} disabled Team Admin {$target->name}.",
        ]);

        $this->withSession($this->fullAdminSession($actor))
            ->patch(route('admin.users.status', $target), ['is_active' => '1', 'current_admin_password' => 'password'])
            ->assertRedirect();

        $this->assertTrue($target->fresh()->is_active);
        $this->assertSame($originalSessionVersion + 1, $target->fresh()->session_version);
        $this->assertDatabaseHas('activity_logs', [
            'user_id' => $actor->id,
            'action' => 'Enabled administrator',
        ]);
    }

    public function test_disabled_team_admin_cannot_log_in_but_can_log_in_after_enable(): void
    {
        $admin = User::factory()->create([
            'role' => 'limited',
            'email' => 'disabled.staff@example.test',
            'password' => 'password',
            'is_active' => false,
        ]);

        $this->post(route('admin.login.post'), [
            'email' => $admin->email,
            'password' => 'password',
        ])->assertSessionHas('error', 'Your administrator account has been disabled. Please contact a primary administrator.');

        $admin->update(['is_active' => true]);

        $this->post(route('admin.login.post'), [
            'email' => $admin->email,
            'password' => 'password',
        ])->assertRedirect(route('admin.dashboard'));

        $this->assertSame($admin->id, session('admin_user_id'));
    }

    public function test_active_database_primary_admin_can_log_in_with_its_hashed_password(): void
    {
        $admin = User::factory()->create([
            'role' => 'full',
            'email' => 'active.primary@example.test',
            'password' => 'secure-database-password',
            'is_active' => true,
        ]);

        $this->post(route('admin.login.post'), [
            'email' => $admin->email,
            'password' => 'secure-database-password',
        ])->assertRedirect(route('admin.dashboard'));

        $this->assertSame($admin->id, session('admin_user_id'));
        $this->assertSame('full', session('admin_role'));
    }

    public function test_disabled_database_primary_admin_cannot_log_in(): void
    {
        $email = 'disabled.primary@example.test';
        $password = 'disabled-primary-secret';
        User::factory()->create([
            'role' => 'full',
            'email' => $email,
            'password' => $password,
            'is_active' => false,
        ]);

        $this->post(route('admin.login.post'), [
            'email' => $email,
            'password' => $password,
        ])->assertSessionHas('error', 'Your administrator account has been disabled. Please contact a primary administrator.');
    }

    public function test_disabled_primary_admin_can_log_in_again_after_being_reenabled(): void
    {
        User::factory()->create(['role' => 'full', 'is_active' => true]);
        $admin = User::factory()->create([
            'role' => 'full',
            'email' => 'reenabled.primary@example.test',
            'password' => 'primary-reenable-secret',
            'is_active' => false,
        ]);

        $this->post(route('admin.login.post'), [
            'email' => $admin->email,
            'password' => 'primary-reenable-secret',
        ])->assertSessionHas('error', 'Your administrator account has been disabled. Please contact a primary administrator.');

        $admin->update(['is_active' => true]);

        $this->post(route('admin.login.post'), [
            'email' => $admin->email,
            'password' => 'primary-reenable-secret',
        ])->assertRedirect(route('admin.dashboard'));
        $this->assertSame($admin->id, session('admin_user_id'));
    }

    public function test_one_primary_admin_can_disable_another_while_a_primary_remains_active(): void
    {
        $actor = User::factory()->create(['role' => 'full']);
        $target = User::factory()->create(['role' => 'full']);

        $this->withSession($this->fullAdminSession($actor))
            ->patch(route('admin.users.status', $target), ['is_active' => '0', 'current_admin_password' => 'password'])
            ->assertSessionHas('success', 'Administrator account disabled successfully.');

        $this->assertFalse($target->fresh()->is_active);
        $this->assertTrue($actor->fresh()->is_active);
    }

    public function test_disabled_logged_in_admin_is_logged_out_on_the_next_protected_request(): void
    {
        $admin = User::factory()->create(['role' => 'limited']);
        $admin->update(['is_active' => false]);

        $this->withSession([
            'is_admin' => true,
            'admin_role' => 'limited',
            'admin_user_id' => $admin->id,
            'admin_name' => $admin->name,
            'admin_email' => $admin->email,
        ])->get(route('admin.dashboard'))
            ->assertRedirect(route('admin.login'))
            ->assertSessionHas('error', 'Your administrator account has been disabled. Please contact a primary administrator.');

        $this->assertNull(session('is_admin'));
    }

    public function test_full_admin_can_edit_name_and_change_is_audited_without_rewriting_history(): void
    {
        $actor = User::factory()->create(['role' => 'full']);
        $target = User::factory()->create(['role' => 'limited', 'name' => 'Old Name']);
        $previousHistory = ActivityLog::create([
            'user_id' => $target->id,
            'actor_name' => 'Old Name',
            'actor_email' => $target->email,
            'actor_role' => 'limited',
            'action' => 'Previous action',
            'activity_date' => now()->toDateString(),
            'activity_time' => now()->toTimeString(),
            'description' => 'Logged while the admin used the old name.',
        ]);

        $this->withSession($this->fullAdminSession($actor))
            ->put(route('admin.users.update-name', $target), ['name' => '  Jane Dela Cruz  '])
            ->assertRedirect();

        $this->assertSame('Jane Dela Cruz', $target->fresh()->name);
        $this->assertSame('Old Name', $previousHistory->fresh()->actor_name);
        $this->assertDatabaseHas('activity_logs', [
            'user_id' => $actor->id,
            'action' => 'Changed administrator name',
            'description' => "Changed Team Admin name from 'Old Name' to 'Jane Dela Cruz'.",
        ]);
    }

    public function test_full_admin_can_reset_a_team_password_and_revoke_its_existing_sessions(): void
    {
        $actor = User::factory()->create(['role' => 'full']);
        $target = User::factory()->create(['role' => 'limited', 'password' => 'PreviousPassword#2026']);
        $version = $target->session_version;

        $this->withSession($this->fullAdminSession($actor))
            ->put(route('admin.users.reset', $target), [
                'current_admin_password' => 'password',
                'password' => 'NewTeamPassword#2026',
                'password_confirmation' => 'NewTeamPassword#2026',
            ])
            ->assertSessionHas('success', 'Password updated for '.$target->name.'.');

        $this->assertTrue(Hash::check('NewTeamPassword#2026', $target->fresh()->password));
        $this->assertSame($version + 1, $target->fresh()->session_version);
        $this->assertDatabaseHas('activity_logs', [
            'user_id' => $actor->id,
            'action' => 'Changed administrator password',
            'description' => 'Updated administrator password. Existing sessions were revoked.',
        ]);
    }

    public function test_name_validation_rejects_empty_and_invalid_input(): void
    {
        $actor = User::factory()->create(['role' => 'full']);
        $target = User::factory()->create(['role' => 'limited']);

        foreach (['', '   ', '<script>alert(1)</script>'] as $name) {
            $this->withSession($this->fullAdminSession($actor))
                ->put(route('admin.users.update-name', $target), ['name' => $name])
                ->assertSessionHasErrors('name');
        }
    }

    public function test_admin_cannot_disable_themselves_or_the_last_active_primary_admin(): void
    {
        $onlyPrimaryAdmin = User::factory()->create(['role' => 'full']);

        $this->withSession($this->fullAdminSession($onlyPrimaryAdmin))
            ->patch(route('admin.users.status', $onlyPrimaryAdmin), ['is_active' => '0', 'current_admin_password' => 'password'])
            ->assertSessionHas('error', 'Cannot disable the last active Primary Administrator. At least one active Primary Administrator is required.');
        $onlyPrimaryAdmin->update(['is_active' => false]);

        $target = User::factory()->create(['role' => 'full']);

        $this->withSession([
            'is_admin' => true,
            'admin_role' => 'full',
            'admin_user_id' => null,
            'admin_email' => 'primary@example.test',
            'admin_name' => 'Primary Administrator',
        ])
            ->patch(route('admin.users.status', $target), ['is_active' => '0', 'current_admin_password' => 'some-password'])
            ->assertRedirect(route('admin.login'))
            ->assertSessionHas('error', 'Your administrator session has expired. Please sign in again.');

        $this->assertTrue($target->fresh()->is_active);
    }

    public function test_limited_admin_cannot_manage_admin_accounts(): void
    {
        $limitedAdmin = User::factory()->create(['role' => 'limited']);
        $target = User::factory()->create(['role' => 'limited']);

        $this->withSession([
            'is_admin' => true,
            'admin_role' => 'limited',
            'admin_user_id' => $limitedAdmin->id,
            'admin_email' => $limitedAdmin->email,
        ])->put(route('admin.users.update-name', $target), ['name' => 'Changed'])
            ->assertForbidden();

        $this->patch(route('admin.users.status', $target), ['is_active' => '0'])
            ->assertForbidden();
    }

    private function fullAdminSession(?User $admin = null): array
    {
        return [
            'is_admin' => true,
            'admin_role' => 'full',
            'admin_user_id' => $admin?->id,
            'admin_name' => $admin?->name ?? 'Primary Administrator',
            'admin_email' => $admin?->email ?? 'primary@example.test',
        ];
    }
}
