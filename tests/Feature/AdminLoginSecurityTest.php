<?php

namespace Tests\Feature;

use Tests\TestCase;

class AdminLoginSecurityTest extends TestCase
{
    public function test_correct_primary_admin_credentials_still_log_in(): void
    {
        $response = $this->post('/admin/login', [
            'email' => 'admin@3yos.com',
            'password' => 'admin123',
        ]);

        $response->assertRedirect(route('admin.dashboard'));
        $this->assertTrue(session('is_admin'));
        $this->assertSame('full', session('admin_role'));
    }

    public function test_wrong_password_is_rejected(): void
    {
        $response = $this->post('/admin/login', [
            'email' => 'admin@3yos.com',
            'password' => 'not-the-password',
        ]);

        $response->assertSessionHas('error', 'Invalid admin credentials.');
        $this->assertNull(session('is_admin'));
    }

    public function test_repeated_failed_attempts_are_rate_limited(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $this->post('/admin/login', ['email' => 'admin@3yos.com', 'password' => 'wrong'])
                ->assertSessionHas('error', 'Invalid admin credentials.');
        }

        // The 6th attempt is blocked before credentials are even checked — even with the
        // correct password, since the lockout protects against a compromised/guessed password too.
        $response = $this->post('/admin/login', [
            'email' => 'admin@3yos.com',
            'password' => 'admin123',
        ]);

        $response->assertSessionHas('error');
        $this->assertStringContainsString('Too many login attempts', session('error'));
        $this->assertNull(session('is_admin'));
    }

    public function test_successful_login_clears_the_rate_limit_counter(): void
    {
        $this->post('/admin/login', ['email' => 'admin@3yos.com', 'password' => 'wrong']);
        $this->post('/admin/login', ['email' => 'admin@3yos.com', 'password' => 'wrong']);

        $this->post('/admin/login', ['email' => 'admin@3yos.com', 'password' => 'admin123'])
            ->assertRedirect(route('admin.dashboard'));

        // Further failed attempts start counting fresh, not continuing from before the success.
        for ($i = 0; $i < 4; $i++) {
            $this->post('/admin/login', ['email' => 'admin@3yos.com', 'password' => 'wrong'])
                ->assertSessionHas('error', 'Invalid admin credentials.');
        }
    }

    public function test_limited_admin_cannot_access_backups(): void
    {
        $response = $this->withSession([
            'is_admin' => true,
            'admin_role' => 'limited',
        ])->get(route('admin.backups'));

        $response->assertForbidden();
    }

    public function test_full_admin_can_still_access_backups(): void
    {
        $response = $this->withSession([
            'is_admin' => true,
            'admin_role' => 'full',
        ])->get(route('admin.backups'));

        $response->assertOk();
    }
}
