<?php

namespace Tests\Feature;

use App\Models\ActivityLog;
use App\Models\Package;
use App\Models\Reservation;
use App\Models\ReservationPayment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\AcceptsReservations;
use Tests\TestCase;

class ReservationAcceptanceRequirementsTest extends TestCase
{
    use RefreshDatabase;
    use AcceptsReservations;

    private const ADMIN = ['is_admin' => true, 'admin_role' => 'full', 'admin_name' => 'Acceptance Tester', 'admin_email' => 'acceptance@example.com'];

    private function reservation(array $overrides = []): Reservation
    {
        $package = Package::create(['name' => 'Acceptance Package', 'slug' => 'acceptance-'.uniqid(), 'price' => 750]);

        return Reservation::create($overrides + [
            'package_id' => $package->id,
            'full_name' => 'Acceptance Client',
            'contact_number' => '09171234567',
            'email' => 'acceptance-'.uniqid().'@example.com',
            'address' => '1 Acceptance Street',
            'event_type' => 'Wedding',
            'event_date' => now()->addMonth()->toDateString(),
            'event_time' => '18:00',
            'venue' => 'Acceptance Hall',
            'guest_count' => 80,
            'estimated_budget' => 60000,
            'total_cost' => null,
            'status' => Reservation::STATUS_PENDING,
        ]);
    }

    public function test_acceptance_requires_the_contract_payment_receipt_and_receipt_review(): void
    {
        $reservation = $this->reservation();
        $file = \Illuminate\Http\UploadedFile::fake()->image('receipt.png');

        $response = $this->withSession(self::ADMIN)->post(route('admin.reservations.accept', $reservation), [
            'total_cost' => 1000,
            'payment_date' => now()->toDateString(),
            'payment_type' => 'Downpayment',
            'amount' => 100,
            'payment_method' => 'GCash',
        ]);

        $response->assertSessionHasErrors(['receipt_image', 'receipt_confirmed', 'receipt_review_token']);
        $this->assertSame(Reservation::STATUS_PENDING, $reservation->fresh()->status);
        $this->assertNull($reservation->fresh()->total_cost);
        $this->assertSame(0, ReservationPayment::count());
    }

    public function test_downpayment_acceptance_saves_contract_payment_receipt_and_status_together(): void
    {
        $reservation = $this->reservation();

        $this->postReservationAcceptance($reservation, self::ADMIN, ['total_cost' => 1000, 'amount' => 250])
            ->assertSessionHasNoErrors();

        $reservation->refresh();
        $payment = $reservation->payments()->firstOrFail();
        $this->assertSame(Reservation::STATUS_CONFIRMED, $reservation->status);
        $this->assertSame(1000.0, (float) $reservation->total_cost);
        $this->assertSame(250.0, (float) $payment->amount);
        $this->assertSame('Downpayment', $payment->payment_type);
        $this->assertNotNull($payment->receipt_sha256);
        $this->assertTrue(Storage::disk('local')->exists($payment->receipt_image_path));
        $this->assertSame(750.0, (float) $reservation->balance);
        $this->assertSame(1, ActivityLog::where('action', 'Reservation status changed')->count());
        $this->assertSame(1, ActivityLog::where('action', 'Payment recorded')->count());
    }

    public function test_initial_payment_cannot_exceed_the_contract_price(): void
    {
        $reservation = $this->reservation();

        $this->postReservationAcceptance($reservation, self::ADMIN, ['total_cost' => 100, 'amount' => 101])
            ->assertSessionHasErrors('amount');

        $this->assertSame(Reservation::STATUS_PENDING, $reservation->fresh()->status);
        $this->assertNull($reservation->fresh()->total_cost);
        $this->assertSame(0, ReservationPayment::count());
        $this->assertSame(0, ActivityLog::where('action', 'Reservation status changed')->count());
    }

    public function test_full_payment_must_equal_the_contract_price(): void
    {
        $reservation = $this->reservation();

        $this->postReservationAcceptance($reservation, self::ADMIN, [
            'total_cost' => 1000,
            'payment_type' => 'Full Payment',
            'amount' => 999,
        ])->assertSessionHasErrors('amount');

        $this->assertSame(Reservation::STATUS_PENDING, $reservation->fresh()->status);
        $this->assertNull($reservation->fresh()->total_cost);
        $this->assertSame(0, ReservationPayment::count());
    }

    public function test_full_payment_can_accept_and_fully_pay_in_one_submission(): void
    {
        $reservation = $this->reservation();

        $this->postReservationAcceptance($reservation, self::ADMIN, [
            'total_cost' => 1000,
            'payment_type' => 'Full Payment',
            'amount' => 1000,
        ])->assertSessionHasNoErrors();

        $this->assertSame(Reservation::STATUS_CONFIRMED, $reservation->fresh()->status);
        $this->assertSame(0.0, (float) $reservation->fresh()->balance);
        $this->assertSame('Full Payment', $reservation->payments()->sole()->payment_type);
    }

    public function test_duplicate_transaction_reference_does_not_accept_or_record_a_second_payment(): void
    {
        $first = $this->reservation();
        $this->postReservationAcceptance($first, self::ADMIN)->assertSessionHasNoErrors();

        $second = $this->reservation();
        $this->postReservationAcceptance(
            $second,
            self::ADMIN,
            ['reference_number' => '341897185'],
            "Paid via GCash\nAmount 50.00\nDate Oct 03, 2026\nPayment successful",
        )
            ->assertSessionHasErrors('reference_number');

        $this->assertSame(Reservation::STATUS_PENDING, $second->fresh()->status);
        $this->assertSame(0, $second->payments()->count());
        $this->assertSame(1, ReservationPayment::count());
        $this->assertSame(1, ActivityLog::where('action', 'Duplicate receipt blocked')->count());
    }

    public function test_acceptance_form_is_only_rendered_for_pending_reservations(): void
    {
        $pending = $this->reservation();
        $this->withSession(self::ADMIN)->get(route('admin.reservations.show', $pending))
            ->assertOk()
            ->assertSee('Set contract and review initial payment')
            ->assertSee('Confirm Payment &amp; Accept Reservation', false)
            ->assertSee('data-receipt-preview-trigger', false)
            ->assertSee('data-acceptance-receipt-preview', false)
            ->assertSee('dialog.showModal()', false)
            ->assertSee('dialog.close()', false)
            ->assertSee('id="acceptance-receipt-lightbox"', false)
            ->assertSee('Official receipt')
            ->assertSee('data-acceptance-receipt-zoom', false)
            ->assertSee('Fit to screen')
            ->assertSee('data-acceptance-receipt-close', false)
            ->assertSee("dialog.addEventListener('cancel'", false)
            ->assertSee("if (event.target === dialog) closePreview()", false)
            ->assertSee('data-require-receipt-review', false)
            ->assertSee('previewTrigger.hidden = true', false)
            ->assertSee('previewTrigger.hidden = false', false)
            ->assertDontSee('Existing payment receipts')
            ->assertDontSee('id="acceptance-receipt-dialog"', false)
            ->assertDontSee('data-toggle-acceptance-receipt-zoom', false);

        $confirmed = $this->reservation(['status' => Reservation::STATUS_CONFIRMED]);
        $this->withSession(self::ADMIN)->get(route('admin.reservations.show', $confirmed))
            ->assertOk()
            ->assertDontSee('id="reservation-acceptance-form"', false);
    }

    public function test_acceptance_form_does_not_display_existing_payment_receipts(): void
    {
        Storage::fake('local');
        $reservation = $this->reservation();
        $png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=', true);
        $firstPath = 'payment-receipts/acceptance-first.png';
        $secondPath = 'payment-receipts/acceptance-second.png';
        Storage::disk('local')->put($firstPath, $png);
        Storage::disk('local')->put($secondPath, $png);

        $firstPayment = $reservation->payments()->create([
            'payment_date' => now()->toDateString(),
            'payment_type' => 'Downpayment',
            'amount' => 100,
            'payment_method' => 'GCash',
            'receipt_image_path' => $firstPath,
        ]);
        $secondPayment = $reservation->payments()->create([
            'payment_date' => now()->toDateString(),
            'payment_type' => 'Partial Payment',
            'amount' => 50,
            'payment_method' => 'Cash',
            'receipt_image_path' => $secondPath,
        ]);
        $paymentWithoutReceipt = $reservation->payments()->create([
            'payment_date' => now()->toDateString(),
            'payment_type' => 'Partial Payment',
            'amount' => 25,
            'payment_method' => 'Cash',
        ]);

        $firstReceiptUrl = route('admin.reservations.payments.receipt', [$reservation, $firstPayment]);
        $secondReceiptUrl = route('admin.reservations.payments.receipt', [$reservation, $secondPayment]);
        $response = $this->withSession(self::ADMIN)->get(route('admin.reservations.show', $reservation));
        $response->assertOk()
            ->assertDontSee($firstReceiptUrl)
            ->assertDontSee($secondReceiptUrl)
            ->assertDontSee('Existing payment receipts')
            ->assertDontSee('No receipt uploaded.')
            ->assertSee('data-acceptance-receipt-preview', false)
            ->assertSee('data-receipt-file', false);

        $this->withSession(self::ADMIN)->get($firstReceiptUrl)
            ->assertOk()
            ->assertHeader('Content-Type', 'image/png');
        $this->withSession(self::ADMIN)->get($secondReceiptUrl)
            ->assertOk()
            ->assertHeader('Content-Type', 'image/png');

        $missingPath = 'payment-receipts/missing.png';
        $paymentWithoutReceipt->update(['receipt_image_path' => $missingPath]);
        $missingReceiptUrl = route('admin.reservations.payments.receipt', [$reservation, $paymentWithoutReceipt]);
        $this->withSession(self::ADMIN)->get(route('admin.reservations.show', $reservation))
            ->assertOk()
            ->assertDontSee($missingReceiptUrl);
        $this->withSession(self::ADMIN)->get($missingReceiptUrl)->assertNotFound();
    }
}
