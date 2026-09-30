<?php

namespace Tests\Feature;

use App\Models\ActivityLog;
use App\Models\Package;
use App\Models\Reservation;
use App\Models\ReservationPayment;
use App\Models\ReservationRefund;
use App\Services\BackupService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class ReservationPaymentTest extends TestCase
{
    use RefreshDatabase;

    private const ADMIN = ['is_admin' => true, 'admin_role' => 'full', 'admin_name' => 'Payment Tester', 'admin_email' => 'tester@3yos.com'];

    private function reservation(array $overrides = []): Reservation
    {
        $package = Package::create(['name' => 'Gold', 'slug' => 'gold-'.uniqid(), 'price' => 750, 'min_guests' => 20, 'max_guests' => 200]);

        return Reservation::create($overrides + [
            'package_id' => $package->id,
            'full_name' => 'Payment Client',
            'contact_number' => '09171234567',
            'email' => 'payment@example.com',
            'address' => '1 Payment Street',
            'event_type' => 'Wedding',
            'event_date' => now()->addMonth()->toDateString(),
            'event_time' => '18:00',
            'venue' => 'Payment Hall',
            'guest_count' => 100,
            'estimated_budget' => 75000,
            'total_cost' => 1000,
            'status' => 'confirmed',
            'reservation_code' => 'RES-PAY'.random_int(1000, 9999),
        ]);
    }

    private function pay(Reservation $reservation, float $amount, array $overrides = [])
    {
        return $this->withSession(self::ADMIN)->post(route('admin.reservations.payments.store', $reservation), $overrides + [
            'payment_date' => now()->toDateString(),
            'payment_type' => 'Downpayment',
            'amount' => $amount,
            'payment_method' => 'Cash',
        ]);
    }

    private function refund(Reservation $reservation, float $amount, array $overrides = [])
    {
        return $this->withSession(self::ADMIN)->post(route('admin.reservations.refunds.store', $reservation), $overrides + [
            'request_key' => (string) Str::uuid(),
            'refund_date' => now()->toDateString(),
            'refund_amount' => $amount,
            'refund_method' => 'Cash',
        ]);
    }

    private function pngUpload(string $name = 'receipt.png'): UploadedFile
    {
        return UploadedFile::fake()->createWithContent(
            $name,
            base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII='),
        );
    }

    private function assertTotals(Reservation $reservation, float $paid, float $balance, string $status): void
    {
        $fresh = $reservation->fresh();
        $this->assertSame($paid, (float) $fresh->amount_paid, 'total paid');
        $this->assertSame($balance, (float) $fresh->balance, 'balance');
        $this->assertSame($status, $fresh->payment_status, 'status');
    }

    public function test_payments_accumulate_and_status_moves_from_downpayment_to_fully_paid(): void
    {
        $reservation = $this->reservation();

        // TEST 1
        $this->pay($reservation, 100)->assertRedirect(route('admin.reservations.payments', $reservation))->assertSessionHas('success');
        $this->assertTotals($reservation, 100.0, 900.0, 'Downpayment');

        // TEST 2
        $this->pay($reservation, 400, ['payment_type' => 'Partial Payment', 'payment_method' => 'GCash']);
        $this->assertTotals($reservation, 500.0, 500.0, 'Partial Payment');

        // TEST 3
        $this->pay($reservation, 500, ['payment_type' => 'Final Payment', 'payment_method' => 'Bank Transfer']);
        $this->assertTotals($reservation, 1000.0, 0.0, 'Fully Paid');
        $this->assertSame('Final Payment', $reservation->fresh()->payment_type);
        $this->assertSame(3, $reservation->payments()->count());
    }

    public function test_receipt_image_is_optional_and_payment_totals_remain_unchanged(): void
    {
        Storage::fake('local');
        $reservation = $this->reservation();

        $this->pay($reservation, 100)->assertRedirect();

        $payment = $reservation->payments()->firstOrFail();
        $this->assertNull($payment->receipt_image_path);
        $this->assertTotals($reservation, 100.0, 900.0, 'Downpayment');
        $this->withSession(self::ADMIN)
            ->get(route('admin.reservations.payments', $reservation))
            ->assertOk()
            ->assertSee('No receipt');
    }

    public function test_admin_can_upload_preview_replace_and_delete_payment_receipts(): void
    {
        Storage::fake('local');
        Storage::fake('public');
        $reservation = $this->reservation();
        $url = route('admin.reservations.payments.store', $reservation);

        $this->withSession(self::ADMIN)->post($url, [
            'payment_date' => now()->toDateString(),
            'payment_type' => 'Downpayment',
            'amount' => 100,
            'payment_method' => 'GCash',
            'receipt_image' => $this->pngUpload(),
        ])->assertRedirect();

        $payment = $reservation->payments()->firstOrFail();
        $oldPath = $payment->receipt_image_path;
        $this->assertNotNull($oldPath);
        Storage::disk('local')->assertExists($oldPath);
        Storage::disk('public')->assertMissing($oldPath);
        $this->assertTotals($reservation, 100.0, 900.0, 'Downpayment');

        $receiptUrl = route('admin.reservations.payments.receipt', [$reservation, $payment]);
        $this->withSession(self::ADMIN)
            ->get(route('admin.reservations.payments', $reservation))
            ->assertOk()
            ->assertSee('View Receipt')
            ->assertSee($receiptUrl);
        $this->withSession(self::ADMIN)
            ->get($receiptUrl)
            ->assertOk()
            ->assertHeader('Content-Type', 'image/png')
            ->assertHeader('X-Content-Type-Options', 'nosniff');
        $this->flushSession();
        $this->get($receiptUrl)->assertRedirect(route('admin.login'));

        $this->withSession(self::ADMIN)->post(route('admin.reservations.payments.update', [$reservation, $payment]), [
            '_method' => 'PUT',
            'payment_date' => now()->toDateString(),
            'payment_type' => 'Downpayment',
            'amount' => 100,
            'payment_method' => 'GCash',
        ])->assertRedirect();
        $this->assertSame($oldPath, $payment->fresh()->receipt_image_path);
        Storage::disk('local')->assertExists($oldPath);

        $this->withSession(self::ADMIN)->post(route('admin.reservations.payments.update', [$reservation, $payment]), [
            '_method' => 'PUT',
            'payment_date' => now()->toDateString(),
            'payment_type' => 'Downpayment',
            'amount' => 100,
            'payment_method' => 'GCash',
            'receipt_image' => $this->pngUpload('replacement.png'),
        ])->assertRedirect();

        $newPath = $payment->fresh()->receipt_image_path;
        $this->assertNotSame($oldPath, $newPath);
        Storage::disk('local')->assertMissing($oldPath);
        Storage::disk('local')->assertExists($newPath);
        $this->assertTotals($reservation, 100.0, 900.0, 'Downpayment');

        Storage::disk('local')->delete($newPath);
        $this->withSession(self::ADMIN)->get($receiptUrl)->assertNotFound();

        $this->withSession(self::ADMIN)
            ->delete(route('admin.reservations.payments.destroy', [$reservation, $payment]))
            ->assertRedirect();
        Storage::disk('local')->assertMissing($newPath);
        $this->assertTotals($reservation, 0.0, 1000.0, 'Unpaid');
    }

    public function test_payment_receipt_upload_validates_images_and_receipt_preview_requires_admin(): void
    {
        Storage::fake('local');
        $reservation = $this->reservation();
        $fields = [
            'payment_date' => now()->toDateString(),
            'payment_type' => 'Downpayment',
            'amount' => 100,
            'payment_method' => 'Cash',
            'receipt_image' => UploadedFile::fake()->create('receipt.txt', 10, 'text/plain'),
        ];

        $this->withSession(self::ADMIN)
            ->post(route('admin.reservations.payments.store', $reservation), $fields)
            ->assertSessionHasErrors('receipt_image');
        $this->assertSame(0, $reservation->payments()->count());

        $this->pay($reservation, 100);
        $payment = $reservation->payments()->firstOrFail();
        $this->flushSession();
        $this->get(route('admin.reservations.payments.receipt', [$reservation, $payment]))
            ->assertRedirect(route('admin.login'));
    }

    public function test_payment_receipt_image_cannot_exceed_five_megabytes(): void
    {
        Storage::fake('local');
        $reservation = $this->reservation();
        $validPng = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=');

        $this->withSession(self::ADMIN)
            ->post(route('admin.reservations.payments.store', $reservation), [
                'payment_date' => now()->toDateString(),
                'payment_type' => 'Downpayment',
                'amount' => 100,
                'payment_method' => 'Cash',
                'receipt_image' => UploadedFile::fake()->createWithContent('large.png', $validPng.str_repeat('0', 5 * 1024 * 1024)),
            ])
            ->assertSessionHasErrors('receipt_image');

        $this->assertSame(0, $reservation->payments()->count());
        $this->assertSame([], Storage::disk('local')->allFiles('payment-receipts'));
    }

    public function test_payment_larger_than_the_remaining_balance_is_rejected(): void
    {
        $reservation = $this->reservation();

        // TEST 4
        $this->pay($reservation, 1500)->assertSessionHasErrors(['amount' => 'The amount cannot exceed the remaining balance of ₱1,000.00.']);
        $this->assertSame(0, $reservation->payments()->count());
        $this->assertTotals($reservation, 0.0, 0.0, 'Unpaid');
    }

    public function test_deleting_a_payment_recalculates_totals(): void
    {
        $reservation = $this->reservation();
        $this->pay($reservation, 100);
        $this->pay($reservation, 400, ['payment_type' => 'Partial Payment']);
        $fourHundred = $reservation->payments()->where('amount', 400)->firstOrFail();

        // TEST 5
        $this->withSession(self::ADMIN)
            ->delete(route('admin.reservations.payments.destroy', [$reservation, $fourHundred]))
            ->assertSessionHas('success');

        $this->assertModelMissing($fourHundred);
        $this->assertTotals($reservation, 100.0, 900.0, 'Downpayment');
    }

    public function test_editing_a_payment_recalculates_and_cannot_exceed_the_contract(): void
    {
        $reservation = $this->reservation();
        $this->pay($reservation, 100);
        $payment = $reservation->payments()->firstOrFail();
        $fields = ['payment_date' => now()->toDateString(), 'payment_type' => 'Downpayment', 'payment_method' => 'GCash'];

        $this->withSession(self::ADMIN)->put(route('admin.reservations.payments.update', [$reservation, $payment]), $fields + ['amount' => 1000]);
        $this->assertTotals($reservation, 1000.0, 0.0, 'Fully Paid');

        $this->withSession(self::ADMIN)->put(route('admin.reservations.payments.update', [$reservation, $payment]), $fields + ['amount' => 1000.01])
            ->assertSessionHasErrorsIn('editPayment', 'amount')
            ->assertSessionHas('editing_payment', $payment->id);
        $this->assertSame(1000.0, (float) $payment->fresh()->amount);
    }

    public function test_payment_validation_messages(): void
    {
        $reservation = $this->reservation();

        $this->withSession(self::ADMIN)->post(route('admin.reservations.payments.store', $reservation), [])
            ->assertSessionHasErrors(['payment_date', 'payment_type', 'amount', 'payment_method']);
        $this->pay($reservation, 0)->assertSessionHasErrors(['amount' => 'The amount must be greater than ₱0.00.']);
        $this->pay($reservation, -50)->assertSessionHasErrors('amount');
        $this->pay($reservation, 10.555)->assertSessionHasErrors('amount');
        $this->pay($reservation, 100, ['payment_method' => 'Crypto'])->assertSessionHasErrors('payment_method');
        $this->pay($reservation, 100, ['payment_date' => now()->addDay()->toDateString()])->assertSessionHasErrors(['payment_date' => 'The payment date cannot be in the future.']);
        $this->assertSame(0, $reservation->payments()->count());
    }

    public function test_payments_require_a_contract_price_and_contract_cannot_drop_below_paid(): void
    {
        $noContract = $this->reservation(['total_cost' => null]);
        $this->pay($noContract, 100)->assertSessionHasErrors(['amount' => 'Set the contract price before recording payments.']);

        $reservation = $this->reservation();
        $this->pay($reservation, 600);
        $this->withSession(self::ADMIN)->patch(route('admin.reservations.payments.details', $reservation), ['total_cost' => 500])
            ->assertSessionHasErrors('total_cost');
        $this->assertSame(1000.0, (float) $reservation->fresh()->total_cost);

        $this->withSession(self::ADMIN)->patch(route('admin.reservations.payments.details', $reservation), ['total_cost' => 600, 'payment_due_date' => '2026-10-15'])
            ->assertSessionHas('success');
        $fresh = $reservation->fresh();
        $this->assertSame('2026-10-15', $fresh->payment_due_date->toDateString());
        $this->assertNotSame($fresh->event_date, $fresh->payment_due_date->toDateString(), 'due date is independent of the event date');
        $this->assertTotals($reservation, 600.0, 0.0, 'Fully Paid');
    }

    public function test_a_double_submitted_payment_is_only_recorded_once(): void
    {
        $reservation = $this->reservation();

        $this->pay($reservation, 250)->assertSessionHas('success');
        $this->pay($reservation, 250)->assertSessionHas('success', 'That payment was already recorded, so the repeat submission was ignored.');

        $this->assertSame(1, $reservation->payments()->count());
        $this->assertTotals($reservation, 250.0, 750.0, 'Downpayment');
    }

    public function test_payment_page_shows_summary_history_and_records_who_paid(): void
    {
        $reservation = $this->reservation(['payment_due_date' => '2026-10-15']);
        $this->pay($reservation, 100, ['notes' => 'Reference 12345']);

        $payment = $reservation->payments()->firstOrFail();
        $this->assertSame('Payment Tester', $payment->recorded_by_name);

        $this->withSession(self::ADMIN)->get(route('admin.reservations.payments', $reservation))
            ->assertOk()
            ->assertSeeInOrder(['Contract price', '₱1,000.00', 'Payment status', 'Downpayment', 'Gross paid', '₱100.00', 'Net paid', '₱100.00', 'Remaining balance', '₱900.00', 'Payment due date', 'October 15, 2026'])
            ->assertSee('Reference 12345')
            ->assertSee('Payment history');

        $this->withSession(self::ADMIN)->get(route('admin.reservations.payments.print', $reservation))
            ->assertOk()->assertSee('Payment record')->assertSee('₱900.00');

        // The reservation list links to the detail page; the detail page links onward to payment history.
        $this->withSession(self::ADMIN)->get(route('admin.reservations'))
            ->assertOk()->assertSee(route('admin.reservations.show', $reservation), false);
        $this->withSession(self::ADMIN)->get(route('admin.reservations.show', $reservation))
            ->assertOk()->assertSee(route('admin.reservations.payments', $reservation), false);

        $this->assertTrue(ActivityLog::where('action', 'Recorded payment')->where('description', 'like', '%₱100.00 Cash payment%')->exists());
    }

    public function test_partial_refunds_preserve_the_payment_ledger_and_are_idempotent(): void
    {
        $reservation = $this->reservation();
        $this->pay($reservation, 800);
        $requestKey = (string) Str::uuid();

        $this->refund($reservation, 200, ['request_key' => $requestKey, 'reason' => 'Event change'])
            ->assertRedirect(route('admin.reservations.payments', $reservation))
            ->assertSessionHas('success', 'Refund of ₱200.00 processed.');

        $refund = $reservation->refunds()->firstOrFail();
        $this->assertSame(200.0, (float) $refund->amount);
        $this->assertSame('Event change', $refund->reason);
        $this->assertSame('Payment Tester', $refund->recorded_by_name);
        $this->assertSame(1, $reservation->payments()->count());
        $this->assertSame('confirmed', $reservation->fresh()->status);
        $this->assertTotals($reservation, 600.0, 400.0, 'Partial Payment');
        $this->assertSame(1, ActivityLog::where('action', 'Processed refund')->count());

        $this->withSession(self::ADMIN)->get(route('admin.reservations.payments', $reservation))
            ->assertOk()
            ->assertSee('Gross paid')
            ->assertSee('Total refunded')
            ->assertSee('Net paid')
            ->assertSee('Event change')
            ->assertSee('−₱200.00');
        $this->withSession(self::ADMIN)->get(route('admin.reservations.payments.print', $reservation))
            ->assertOk()
            ->assertSee('Refund')
            ->assertSee('−₱200.00')
            ->assertSee('Remaining balance');
        // The refund breakdown lives on the reservation detail page, not the summary list.
        $this->withSession(self::ADMIN)->get(route('admin.reservations.show', $reservation))
            ->assertOk()
            ->assertSee('Refunded')
            ->assertSee('&#8369;200.00', false);
        $this->withSession(self::ADMIN)->get(route('admin.reservations.export'))
            ->assertOk()
            ->assertSee('"Gross Paid",Refunded,"Net Paid",Balance', false)
            ->assertSee(',800.00,200.00,600.00,400.00', false);

        $this->refund($reservation, 200, ['request_key' => $requestKey])
            ->assertSessionHas('success', 'That refund submission was already processed.');
        $this->assertSame(1, $reservation->refunds()->count());
        $this->assertSame(1, ActivityLog::where('action', 'Processed refund')->count());
        $this->assertTotals($reservation, 600.0, 400.0, 'Partial Payment');
    }

    public function test_full_refund_resets_net_paid_without_changing_reservation_status(): void
    {
        $reservation = $this->reservation();
        $this->pay($reservation, 1000, ['payment_type' => 'Full Payment']);

        $this->refund($reservation, 400)->assertSessionHas('success', 'Refund of ₱400.00 processed.');
        $this->assertTotals($reservation, 600.0, 400.0, 'Partial Payment');
        $this->refund($reservation, 600)->assertSessionHas('success', 'Refund of ₱600.00 processed.');

        $this->assertTotals($reservation, 0.0, 1000.0, 'Unpaid');
        $this->assertSame('confirmed', $reservation->fresh()->status);
        $this->assertSame(1000.0, (float) $reservation->payments()->firstOrFail()->amount);
        $this->assertSame(2, $reservation->refunds()->count());
        $this->assertEquals(1000.0, $reservation->refunds()->sum('amount'));
    }

    public function test_customer_can_pay_again_after_a_partial_refund(): void
    {
        $reservation = $this->reservation();
        $this->pay($reservation, 800);
        $this->refund($reservation, 300);

        $this->pay($reservation, 500, ['payment_type' => 'Final Payment'])
            ->assertSessionHas('success', 'Payment of ₱500.00 recorded.');

        $this->assertTotals($reservation, 1000.0, 0.0, 'Fully Paid');
        $this->assertEquals(1300.0, $reservation->payments()->sum('amount'));
        $this->assertEquals(300.0, $reservation->refunds()->sum('amount'));
    }

    public function test_refunds_cannot_exceed_net_paid_or_use_invalid_amounts_or_methods(): void
    {
        $reservation = $this->reservation();
        $this->pay($reservation, 500);

        $this->refund($reservation, 600)->assertSessionHasErrors(['refund_amount' => 'Refund cannot exceed the total amount paid.']);
        $this->refund($reservation, 0)->assertSessionHasErrors('refund_amount');
        $this->refund($reservation, 10.555)->assertSessionHasErrors('refund_amount');
        $this->refund($reservation, 100, ['refund_method' => 'Crypto'])->assertSessionHasErrors('refund_method');
        $this->refund($reservation, 100, ['refund_date' => now()->addDay()->toDateString()])->assertSessionHasErrors('refund_date');

        $this->assertSame(0, $reservation->refunds()->count());
        $this->assertSame(0, ActivityLog::where('action', 'Processed refund')->count());
        $this->assertTotals($reservation, 500.0, 500.0, 'Downpayment');
    }

    public function test_refunded_amounts_cannot_be_undone_by_editing_or_deleting_payments(): void
    {
        $reservation = $this->reservation();
        $this->pay($reservation, 800);
        $this->refund($reservation, 300);
        $payment = $reservation->payments()->firstOrFail();
        $paymentFields = [
            'payment_date' => now()->toDateString(),
            'payment_type' => 'Downpayment',
            'payment_method' => 'Cash',
        ];

        $this->withSession(self::ADMIN)
            ->put(route('admin.reservations.payments.update', [$reservation, $payment]), $paymentFields + ['amount' => 200])
            ->assertSessionHasErrorsIn('editPayment', 'amount');
        $this->withSession(self::ADMIN)
            ->delete(route('admin.reservations.payments.destroy', [$reservation, $payment]))
            ->assertSessionHasErrors('payment');

        $this->assertModelExists($payment);
        $this->assertTotals($reservation, 500.0, 500.0, 'Partial Payment');

        $this->withSession(self::ADMIN)->patch(route('admin.reservations.payments.details', $reservation), ['total_cost' => 500])
            ->assertSessionHas('success');
        $this->assertTotals($reservation, 500.0, 0.0, 'Fully Paid');
    }

    public function test_bookings_paid_before_the_ledger_keep_their_totals(): void
    {
        $reservation = $this->reservation(['total_cost' => 30000, 'amount_paid' => 8000, 'balance' => 22000, 'payment_status' => 'Downpayment']);

        $this->withSession(self::ADMIN)->get(route('admin.reservations.payments', $reservation))->assertOk();

        $this->assertSame(1, $reservation->payments()->count());
        $this->assertTotals($reservation, 8000.0, 22000.0, 'Downpayment');

        $this->pay($reservation, 2000, ['payment_type' => 'Partial Payment']);
        $this->assertTotals($reservation, 10000.0, 20000.0, 'Partial Payment');

        $fullyPaidWithoutContract = $this->reservation([
            'total_cost' => null,
            'amount_paid' => 8000,
            'balance' => 0,
            'payment_status' => 'Fully Paid',
            'payment_type' => 'Full Payment',
        ]);
        $this->withSession(self::ADMIN)->get(route('admin.reservations.payments', $fullyPaidWithoutContract))->assertOk();
        $this->assertTotals($fullyPaidWithoutContract, 8000.0, 0.0, 'Fully Paid');
    }

    public function test_guests_cannot_see_or_change_payments(): void
    {
        // TEST 6 & 7: the system has no customer accounts, so customers are unauthenticated visitors.
        $reservation = $this->reservation();
        $this->pay($reservation, 100);
        $payment = $reservation->payments()->firstOrFail();
        $this->flushSession();

        $fields = ['payment_date' => now()->toDateString(), 'payment_type' => 'Downpayment', 'amount' => 900, 'payment_method' => 'Cash'];
        $requests = [
            $this->get(route('admin.reservations.payments', $reservation)),
            $this->get(route('admin.reservations.payments.print', $reservation)),
            $this->post(route('admin.reservations.payments.store', $reservation), $fields),
            $this->put(route('admin.reservations.payments.update', [$reservation, $payment]), $fields),
            $this->delete(route('admin.reservations.payments.destroy', [$reservation, $payment])),
            $this->patch(route('admin.reservations.payments.details', $reservation), ['total_cost' => 1]),
            $this->patch(route('admin.reservations.status', $reservation), ['amount_paid' => 1000]),
            $this->post(route('admin.reservations.refunds.store', $reservation), [
                'request_key' => (string) Str::uuid(),
                'refund_date' => now()->toDateString(),
                'refund_amount' => 50,
                'refund_method' => 'Cash',
            ]),
        ];
        foreach ($requests as $response) {
            $response->assertRedirect(route('admin.login'));
        }

        $this->assertSame(1, ReservationPayment::count());
        $this->assertSame(100.0, (float) $payment->fresh()->amount);
        $this->assertTotals($reservation, 100.0, 900.0, 'Downpayment');

        // The customer-facing status page never exposes payment details.
        $this->get(route('reservation.status', ['code' => $reservation->reservation_code]))
            ->assertOk()->assertDontSee('₱900.00')->assertDontSee('Payment history')->assertDontSee('Cash');
    }

    public function test_a_payment_cannot_be_changed_through_another_reservations_url(): void
    {
        $first = $this->reservation();
        $second = $this->reservation();
        $this->pay($first, 100);
        $payment = $first->payments()->firstOrFail();

        $this->withSession(self::ADMIN)->delete(route('admin.reservations.payments.destroy', [$second, $payment]))->assertNotFound();
        $this->assertModelExists($payment);
    }

    public function test_backups_include_payment_and_refund_history(): void
    {
        $reservation = $this->reservation();
        $this->pay($reservation, 300);
        $this->refund($reservation, 100);

        $service = app(BackupService::class);
        $path = $service->pathFor(basename($service->create()));
        try {
            $contents = json_decode(file_get_contents($path), true);
        } finally {
            @unlink($path);
        }

        $this->assertCount(1, $contents['tables']['reservation_payments']);
        $this->assertSame(300.0, (float) $contents['tables']['reservation_payments'][0]['amount']);
        $this->assertArrayHasKey('reservation_refunds', $contents['tables']);
        $this->assertCount(1, $contents['tables']['reservation_refunds']);
        $this->assertSame(100.0, (float) $contents['tables']['reservation_refunds'][0]['amount']);
    }

    public function test_restoring_a_backup_made_before_payment_history_clears_stale_payments(): void
    {
        $reservation = $this->reservation(['total_cost' => 5000, 'amount_paid' => 2000, 'balance' => 3000, 'payment_status' => 'Downpayment']);
        $service = app(BackupService::class);
        $path = $service->create();
        $name = basename($path);

        try {
            // Simulate an older backup file that predates the payments table.
            $backup = json_decode(file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);
            unset($backup['tables']['reservation_payments']);
            unset($backup['tables']['reservation_refunds']);
            file_put_contents($path, json_encode($backup, JSON_THROW_ON_ERROR), LOCK_EX);

            $this->pay($reservation, 500);
            $this->assertSame(2, ReservationPayment::count());
            $this->refund($reservation, 100);
            $this->assertSame(1, ReservationRefund::count());

            $service->restore($name);
        } finally {
            $service->delete($name);
        }

        $this->assertSame(0, ReservationPayment::count());
        $this->assertSame(0, ReservationRefund::count());
        $this->withSession(self::ADMIN)->get(route('admin.reservations.payments', $reservation))->assertOk();
        $this->assertTotals($reservation, 2000.0, 3000.0, 'Downpayment');
    }
}
