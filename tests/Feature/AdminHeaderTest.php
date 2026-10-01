<?php

namespace Tests\Feature;

use Tests\TestCase;

class AdminHeaderTest extends TestCase
{
    private const ADMIN = ['is_admin' => true, 'admin_role' => 'full'];

    public function test_header_keeps_global_actions_and_shows_page_context_without_repeating_brand_kicker(): void
    {
        $response = $this->withSession(self::ADMIN)->get(route('admin.reservations'));

        $response->assertOk();
        $response->assertSee('<header class="header-bar"', false);
        $response->assertSee('Operations workspace');
        $response->assertSee('Manage and review catering reservations');
        $response->assertDontSee('Catering management');
        $response->assertSee('id="themeToggle"', false);
        $response->assertSee('aria-label="Enable dark mode"', false);
        $response->assertSee('href="'.route('home').'" class="header-btn">View website', false);
        $response->assertSee('action="'.route('admin.logout').'"', false);
        $response->assertSee('name="_token"', false);

        $content = $response->getContent();
        $headerStart = strpos($content, '<header class="header-bar"');
        $headerEnd = strpos($content, '</header>', $headerStart);
        $sidebarEnd = strpos($content, '</aside>');
        $this->assertNotFalse($headerStart);
        $this->assertNotFalse($headerEnd);
        $this->assertGreaterThan($sidebarEnd, $headerStart);
        $this->assertStringNotContainsString('themeToggle', substr($content, 0, $sidebarEnd));
        $this->assertStringNotContainsString('View website', substr($content, 0, $sidebarEnd));
        $this->assertStringNotContainsString('Sign out', substr($content, 0, $sidebarEnd));
    }
}
