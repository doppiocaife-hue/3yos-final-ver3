<?php

namespace Tests\Feature;

use App\Models\ActivityLog;
use App\Models\Package;
use App\Models\Reservation;
use App\Models\ReservationPayment;
use App\Services\BackupService;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
            ->assertSeeInOrder(['Contract price', '₱1,000.00', 'Payment status', 'Downpayment', 'Total paid', '₱100.00', 'Remaining balance', '₱900.00', 'Payment due date', 'October 15, 2026'])
            ->assertSee('Reference 12345')
            ->assertSee('Payment history');

        $this->withSession(self::ADMIN)->get(route('admin.reservations.payments.print', $reservation))
            ->assertOk()->assertSee('Payment record')->assertSee('₱900.00');

        $this->withSession(self::ADMIN)->get(route('admin.reservations'))
            ->assertOk()->assertSee(route('admin.reservations.payments', $reservation), false);

        $this->assertTrue(ActivityLog::where('action', 'Recorded payment')->where('description', 'like', '%₱100.00 Cash payment%')->exists());
    }

    public function test_bookings_paid_before_the_ledger_keep_their_totals(): void
    {
        $reservation = $this->reservation(['total_cost' => 30000, 'amount_paid' => 8000, 'balance' => 22000, 'payment_status' => 'Downpayment']);

        $this->withSession(self::ADMIN)->get(route('admin.reservations.payments', $reservation))->assertOk();

        $this->assertSame(1, $reservation->payments()->count());
        $this->assertTotals($reservation, 8000.0, 22000.0, 'Downpayment');

        $this->pay($reservation, 2000, ['payment_type' => 'Partial Payment']);
        $this->assertTotals($reservation, 10000.0, 20000.0, 'Partial Payment');
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

    public function test_backups_include_payment_history(): void
    {
        $reservation = $this->reservation();
        $this->pay($reservation, 300);

        $service = app(BackupService::class);
        $path = $service->pathFor(basename($service->create()));
        try {
            $contents = json_decode(file_get_contents($path), true);
        } finally {
            @unlink($path);
        }

        $this->assertCount(1, $contents['tables']['reservation_payments']);
        $this->assertSame(300.0, (float) $contents['tables']['reservation_payments'][0]['amount']);
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
            file_put_contents($path, json_encode($backup, JSON_THROW_ON_ERROR), LOCK_EX);

            $this->pay($reservation, 500);
            $this->assertSame(2, ReservationPayment::count());

            $service->restore($name);
        } finally {
            $service->delete($name);
        }

        $this->assertSame(0, ReservationPayment::count());
        $this->withSession(self::ADMIN)->get(route('admin.reservations.payments', $reservation))->assertOk();
        $this->assertTotals($reservation, 2000.0, 3000.0, 'Downpayment');
    }
}
