<?php

namespace Tests\Feature;

use App\Models\Inquiry;
use Tests\TestCase;

class InquiryFilteringTest extends TestCase
{
    private const ADMIN = ['is_admin' => true, 'admin_role' => 'full'];

    private function inquiry(array $overrides = []): Inquiry
    {
        return Inquiry::create($overrides + [
            'full_name' => 'Filter Client',
            'contact_number' => '09171234567',
            'email' => 'filter-'.uniqid().'@example.com',
            'subject' => 'Filter subject',
            'category' => 'Catering',
            'message' => 'Please send details.',
            'status' => 'new',
        ]);
    }

    public function test_status_filter_narrows_the_list(): void
    {
        $new = $this->inquiry(['status' => 'new', 'subject' => 'New one']);
        $responded = $this->inquiry(['status' => 'responded', 'subject' => 'Responded one']);

        $response = $this->withSession(self::ADMIN)->get(route('admin.inquiries', ['status' => 'responded']));

        $response->assertOk();
        $response->assertSee('Responded one');
        $response->assertDontSee('New one');
    }

    public function test_needs_response_filter_only_shows_new_and_in_progress(): void
    {
        $this->inquiry(['status' => 'new', 'subject' => 'Needs response A']);
        $this->inquiry(['status' => 'in_progress', 'subject' => 'Needs response B']);
        $this->inquiry(['status' => 'responded', 'subject' => 'Already handled']);
        $this->inquiry(['status' => 'closed', 'subject' => 'Closed one']);

        $response = $this->withSession(self::ADMIN)->get(route('admin.inquiries', ['needs_response' => 1]));

        $response->assertOk();
        $response->assertSee('Needs response A');
        $response->assertSee('Needs response B');
        $response->assertDontSee('Already handled');
        $response->assertDontSee('Closed one');
    }

    public function test_needs_response_badge_shows_on_dashboard_style_count_and_links_correctly(): void
    {
        $this->inquiry(['status' => 'new']);
        $this->inquiry(['status' => 'new']);

        $response = $this->withSession(self::ADMIN)->get(route('admin.inquiries'));

        $response->assertOk();
        $response->assertSee('2 need a response');
        $response->assertSee(route('admin.inquiries', ['needs_response' => 1]), false);
    }

    public function test_search_matches_subject_and_email(): void
    {
        $this->inquiry(['subject' => 'Wedding catering question', 'email' => 'bride@example.com']);
        $this->inquiry(['subject' => 'Corporate event pricing', 'email' => 'office@example.com']);

        $response = $this->withSession(self::ADMIN)->get(route('admin.inquiries', ['search' => 'wedding']));

        $response->assertOk();
        $response->assertSee('Wedding catering question');
        $response->assertDontSee('Corporate event pricing');
    }
}
