<?php

namespace Tests\Feature;

use App\Models\Package;
use App\Models\Service;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class ModulePasswordConfirmationTest extends TestCase
{
    public function test_package_service_gallery_and_toggle_writes_reject_an_incorrect_password(): void
    {
        $admin = User::factory()->create([
            'role' => 'full',
            'password' => Hash::make('the-correct-password'),
        ]);
        $session = [
            'is_admin' => true,
            'admin_role' => 'full',
            'admin_user_id' => $admin->id,
            'admin_email' => $admin->email,
        ];
        $service = Service::create([
            'name' => 'Protected service',
            'slug' => 'protected-service',
            'is_enabled' => true,
        ]);

        foreach (['admin.packages.store', 'admin.services.store', 'admin.gallery.store'] as $routeName) {
            $response = $this->withSession($session)
                ->from(route('admin.dashboard'))
                ->post(route($routeName), ['password_confirmation' => 'incorrect-password']);

            $response->assertRedirect(route('admin.dashboard'));
            $response->assertSessionHasErrors('password_confirmation');
        }

        $response = $this->withSession($session)
            ->from(route('admin.dashboard'))
            ->patch(route('admin.services.toggle', $service), ['password_confirmation' => 'incorrect-password']);

        $response->assertRedirect(route('admin.dashboard'));
        $response->assertSessionHasErrors('password_confirmation');
    }

    public function test_correct_password_allows_package_creation(): void
    {
        $admin = User::factory()->create([
            'role' => 'full',
            'password' => Hash::make('the-correct-password'),
        ]);

        $response = $this->withSession([
            'is_admin' => true,
            'admin_role' => 'full',
            'admin_user_id' => $admin->id,
            'admin_email' => $admin->email,
        ])->post(route('admin.packages.store'), [
            'password_confirmation' => 'the-correct-password',
            'name' => 'Protected package',
            'price' => 500,
        ]);

        $response->assertRedirect(route('admin.packages.index'));
        $this->assertDatabaseHas('packages', ['name' => 'Protected package']);
    }

    public function test_package_edit_form_uses_the_shared_password_dialog(): void
    {
        $package = Package::create([
            'name' => 'Existing package',
            'slug' => 'existing-package',
            'price' => 500,
            'min_guests' => 10,
            'max_guests' => 50,
        ]);

        $response = $this->withSession([
            'is_admin' => true,
            'admin_role' => 'full',
        ])->get(route('admin.packages.edit', $package));

        $response->assertOk();
        $response->assertSee('data-password-confirm', false);
        $response->assertSee('admin-password-dialog', false);
    }
}