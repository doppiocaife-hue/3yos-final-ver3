<?php

namespace Tests\Feature;

use App\Models\Inquiry;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class InquiryPriorityAndReadTrackingTest extends TestCase
{
    private const ADMIN = ['is_admin' => true, 'admin_role' => 'full'];

    private function inquiry(array $overrides = []): Inquiry
    {
        return Inquiry::create($overrides + [
            'full_name' => 'Priority Client',
            'contact_number' => '09171234567',
            'email' => 'priority-'.uniqid().'@example.com',
            'subject' => 'Priority subject',
            'category' => 'Catering',
            'message' => 'Please send details.',
            'status' => 'new',
        ]);
    }

    public function test_existing_inquiries_default_to_normal_priority(): void
    {
        $inquiry = $this->inquiry();

        $this->assertSame('normal', $inquiry->fresh()->priority);
    }

    public function test_admin_can_change_priority_from_the_detail_page(): void
    {
        $inquiry = $this->inquiry();

        $this->withSession(self::ADMIN)
            ->patch(route('admin.inquiries.priority', $inquiry), ['priority' => 'urgent'])
            ->assertSessionHasNoErrors();

        $this->assertSame('urgent', $inquiry->fresh()->priority);
    }

    public function test_invalid_priority_is_rejected(): void
    {
        $inquiry = $this->inquiry();

        $this->withSession(self::ADMIN)
            ->patch(route('admin.inquiries.priority', $inquiry), ['priority' => 'super-urgent'])
            ->assertSessionHasErrors('priority');

        $this->assertSame('normal', $inquiry->fresh()->priority);
    }

    public function test_viewing_an_inquiry_marks_it_read_but_does_not_change_status(): void
    {
        $inquiry = $this->inquiry(['status' => 'new']);
        $this->assertTrue($inquiry->isUnread());

        $this->withSession(self::ADMIN)->get(route('admin.inquiries.show', $inquiry))->assertOk();

        $inquiry->refresh();
        $this->assertFalse($inquiry->isUnread());
        $this->assertSame('new', $inquiry->status, 'Viewing must not silently change the status.');
    }

    public function test_viewing_twice_keeps_the_original_viewed_at_timestamp(): void
    {
        $inquiry = $this->inquiry(['status' => 'new']);

        $this->withSession(self::ADMIN)->get(route('admin.inquiries.show', $inquiry));
        $firstViewedAt = $inquiry->fresh()->viewed_at;

        $this->travel(5)->minutes();
        $this->withSession(self::ADMIN)->get(route('admin.inquiries.show', $inquiry));

        $this->assertTrue($firstViewedAt->equalTo($inquiry->fresh()->viewed_at));
    }

    public function test_a_failed_reply_attempt_moves_new_to_in_progress(): void
    {
        Mail::shouldReceive('to')->andThrow(new \RuntimeException('SMTP connection refused'));
        $inquiry = $this->inquiry(['status' => 'new']);

        $this->withSession(self::ADMIN)
            ->post(route('admin.inquiries.reply', $inquiry), ['reply' => 'Trying to reply.']);

        $inquiry->refresh();
        $this->assertSame('in_progress', $inquiry->status);
        $this->assertSame('Trying to reply.', $inquiry->admin_reply);
        $this->assertNull($inquiry->replied_at);
    }

    public function test_a_failed_reply_attempt_never_touches_a_responded_or_closed_inquiry(): void
    {
        Mail::shouldReceive('to')->andThrow(new \RuntimeException('SMTP connection refused'));
        $inquiry = $this->inquiry(['status' => 'closed']);

        $this->withSession(self::ADMIN)
            ->post(route('admin.inquiries.reply', $inquiry), ['reply' => 'Trying to reply.']);

        $this->assertSame('closed', $inquiry->fresh()->status);
    }

    public function test_unread_dot_shows_for_unviewed_inquiries_and_disappears_after_viewing(): void
    {
        $inquiry = $this->inquiry(['status' => 'new']);

        // The CSS for .inquiry-card--unread always ships in the page's <style> block, so check for
        // the class actually applied to an element rather than the bare string appearing anywhere.
        $response = $this->withSession(self::ADMIN)->get(route('admin.inquiries'));
        $response->assertSee('class="inquiry-card inquiry-card--unread"', false);

        $this->withSession(self::ADMIN)->get(route('admin.inquiries.show', $inquiry));

        $response = $this->withSession(self::ADMIN)->get(route('admin.inquiries'));
        $response->assertDontSee('class="inquiry-card inquiry-card--unread"', false);
    }
}
