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

    public function test_view_tab_narrows_the_all_inquiries_list(): void
    {
        $this->inquiry(['status' => 'new', 'subject' => 'New one']);
        $this->inquiry(['status' => 'responded', 'subject' => 'Responded one']);

        $response = $this->withSession(self::ADMIN)->get(route('admin.inquiries', ['view' => 'responded']));

        $response->assertOk();
        $response->assertSee('Responded one');
        $response->assertDontSee('New one');
    }

    public function test_priority_filter_narrows_the_list(): void
    {
        $this->inquiry(['priority' => 'urgent', 'subject' => 'Urgent one']);
        $this->inquiry(['priority' => 'low', 'subject' => 'Low one']);

        $response = $this->withSession(self::ADMIN)->get(route('admin.inquiries', ['priority' => 'urgent']));

        $response->assertOk();
        $response->assertSee('Urgent one');
        $response->assertDontSee('Low one');
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

    public function test_search_matches_inquiry_id(): void
    {
        $match = $this->inquiry(['subject' => 'Findable by ID']);
        $other = $this->inquiry(['subject' => 'Not this one']);

        $response = $this->withSession(self::ADMIN)->get(route('admin.inquiries', ['search' => (string) $match->id]));

        $response->assertOk();
        $response->assertSee('Findable by ID');
        $response->assertDontSee('Not this one');
    }

    public function test_needs_attention_section_shows_new_and_unreplied_in_progress_only(): void
    {
        $this->inquiry(['status' => 'new', 'subject' => 'Needs attention A']);
        $this->inquiry(['status' => 'in_progress', 'admin_reply' => null, 'subject' => 'Needs attention B']);
        $this->inquiry(['status' => 'responded', 'admin_reply' => 'Thanks', 'replied_at' => now(), 'subject' => 'Already handled']);
        $this->inquiry(['status' => 'closed', 'subject' => 'Closed one']);

        $response = $this->withSession(self::ADMIN)->get(route('admin.inquiries'));

        $response->assertOk();
        $response->assertSee('Needs attention A');
        $response->assertSee('Needs attention B');
        $response->assertDontSee('Already handled');
        $response->assertDontSee('Closed one');
    }

    public function test_needs_attention_count_and_empty_state(): void
    {
        $response = $this->withSession(self::ADMIN)->get(route('admin.inquiries'));
        $response->assertOk();
        $response->assertSee("You're all caught up", false);

        $this->inquiry(['status' => 'new']);
        $this->inquiry(['status' => 'new']);

        $response = $this->withSession(self::ADMIN)->get(route('admin.inquiries'));
        $response->assertOk();
        $response->assertSee('2 inquiries need your response');
    }

    public function test_needs_attention_is_unaffected_by_all_inquiries_filters(): void
    {
        $this->inquiry(['status' => 'new', 'subject' => 'Always visible when needed']);

        $response = $this->withSession(self::ADMIN)->get(route('admin.inquiries', ['view' => 'closed', 'search' => 'nonsense']));

        $response->assertOk();
        // The "All inquiries" list is filtered down to nothing, but Needs Attention still shows it.
        $response->assertSee('Always visible when needed');
    }
}
