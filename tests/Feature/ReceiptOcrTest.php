<?php

namespace Tests\Feature;

use App\Contracts\ReceiptOcrEngine;
use App\Exceptions\ReceiptOcrUnavailable;
use App\Models\Package;
use App\Models\Reservation;
use App\Models\ReservationPayment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ReceiptOcrTest extends TestCase
{
    use RefreshDatabase;

    private const ADMIN = [
        'is_admin' => true,
        'admin_role' => 'full',
        'admin_name' => 'Receipt Tester',
        'admin_email' => 'receipt-tester@example.com',
    ];

    private const GCASH_TEXT = "Generika Chrysanthemum A\nPaid via GCash\nAmount 50.00\nTotal 50.00\nDate Oct 03, 2026 7:56 PM\nReference No. 341897185\nPayment successful";

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        $this->useOcrText(self::GCASH_TEXT);
    }

    private function useOcrText(string $text): void
    {
        $this->app->instance(ReceiptOcrEngine::class, new class($text) implements ReceiptOcrEngine
        {
            public function __construct(private string $text) {}

            public function recognize(string $imagePath): string
            {
                return $this->text;
            }
        });
    }

    private function reservation(): Reservation
    {
        $package = Package::create([
            'name' => 'Receipt Test Package',
            'slug' => 'receipt-test-'.uniqid(),
            'price' => 500,
            'min_guests' => 1,
            'max_guests' => 100,
        ]);

        return Reservation::create([
            'package_id' => $package->id,
            'full_name' => 'Receipt Test Client',
            'contact_number' => '09170000000',
            'email' => 'receipt@example.com',
            'address' => 'Receipt Test Address',
            'event_type' => 'Wedding',
            'event_date' => now()->addMonth()->toDateString(),
            'event_time' => '18:00',
            'venue' => 'Receipt Hall',
            'guest_count' => 50,
            'estimated_budget' => 5000,
            'total_cost' => 1000,
            'status' => 'confirmed',
            'reservation_code' => 'RES-'.strtoupper(uniqid()),
        ]);
    }

    private function pngUpload(string $name = 'receipt.png', string $suffix = ''): UploadedFile
    {
        $png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=');

        return UploadedFile::fake()->createWithContent($name, $png.$suffix);
    }

    private function analyze(Reservation $reservation, UploadedFile $file, ?ReservationPayment $payment = null)
    {
        $payload = ['receipt_image' => $file];
        if ($payment) {
            $payload['payment_id'] = $payment->id;
        }

        return $this->withSession(self::ADMIN)
            ->withHeaders(['Accept' => 'application/json'])
            ->post(route('admin.reservations.payments.receipt.analyze', $reservation), $payload);
    }

    private function paymentFields(array $overrides = []): array
    {
        return $overrides + [
            'payment_date' => '2026-10-03',
            'payment_type' => 'Downpayment',
            'amount' => '50.00',
            'payment_method' => 'GCash',
            'reference_number' => '341897185',
            'receipt_confirmed' => '1',
        ];
    }

    public function test_receipt_analysis_returns_review_fields_without_saving_a_payment_or_file(): void
    {
        $reservation = $this->reservation();
        $file = $this->pngUpload();

        $response = $this->analyze($reservation, $file)
            ->assertOk()
            ->assertJsonPath('receipt.detected', true)
            ->assertJsonPath('receipt.payment_method', 'GCash')
            ->assertJsonPath('receipt.amount', '50.00')
            ->assertJsonPath('receipt.reference_number', '341897185')
            ->assertJsonPath('receipt.payment_date', '2026-10-03');

        $this->assertNotEmpty($response->json('review_token'));
        $this->assertSame(0, ReservationPayment::count());
        $this->assertSame([], Storage::disk('local')->allFiles('payment-receipts'));
    }

    public function test_blank_invoice_is_rejected_without_saving_a_payment(): void
    {
        $this->useOcrText("SERVICE INVOICE\nNo. 0001\nRECEIVED from __________\nThe sum of _________");
        $reservation = $this->reservation();

        $response = $this->analyze($reservation, $this->pngUpload())
            ->assertUnprocessable()
            ->assertJsonPath('message', 'Receipt Not Detected. The uploaded image does not appear to be a completed payment receipt.');

        $this->assertStringNotContainsString('record the payment manually', $response->json('message'));
        $this->assertSame(0, ReservationPayment::count());
        $this->assertDatabaseHas('activity_logs', ['action' => 'Receipt rejected']);
    }

    public function test_local_ocr_failure_does_not_save_receipt_and_manual_entry_remains_available(): void
    {
        $this->app->instance(ReceiptOcrEngine::class, new class implements ReceiptOcrEngine
        {
            public function recognize(string $imagePath): string
            {
                throw new ReceiptOcrUnavailable('unavailable');
            }
        });
        $reservation = $this->reservation();

        $this->analyze($reservation, $this->pngUpload())
            ->assertStatus(503)
            ->assertJsonPath('message', fn ($message) => str_contains($message, 'Local receipt OCR'));
        $this->assertSame(0, ReservationPayment::count());
        $this->assertSame([], Storage::disk('local')->allFiles('payment-receipts'));

        $this->withSession(self::ADMIN)->post(route('admin.reservations.payments.store', $reservation), [
            'payment_date' => now()->toDateString(),
            'payment_type' => 'Downpayment',
            'amount' => '50.00',
            'payment_method' => 'Cash',
        ])->assertRedirect();
        $this->assertSame(1, ReservationPayment::count());
    }

    public function test_upload_cannot_be_saved_until_analysis_token_and_admin_confirmation_are_submitted(): void
    {
        $reservation = $this->reservation();
        $file = $this->pngUpload();

        $this->withSession(self::ADMIN)->post(route('admin.reservations.payments.store', $reservation), $this->paymentFields([
            'receipt_image' => $file,
        ]))->assertSessionHasErrors('receipt_image');
        $this->assertSame(0, ReservationPayment::count());

        $review = $this->analyze($reservation, $file)->assertOk()->json('review_token');
        $fields = $this->paymentFields([
            'receipt_image' => $file,
            'receipt_review_token' => $review,
            'amount' => '55.00',
        ]);
        unset($fields['receipt_confirmed']);
        $this->withSession(self::ADMIN)->post(route('admin.reservations.payments.store', $reservation), $fields)
            ->assertUnprocessable()
            ->assertJsonPath('errors.receipt_image.0', 'Review the receipt details and confirm them before recording a payment.');
        $this->assertSame(0, ReservationPayment::count());
    }

    public function test_admin_corrections_are_recorded_only_after_explicit_confirmation(): void
    {
        $reservation = $this->reservation();
        $file = $this->pngUpload();
        $review = $this->analyze($reservation, $file)->assertOk()->json('review_token');

        $this->withSession(self::ADMIN)->post(route('admin.reservations.payments.store', $reservation), $this->paymentFields([
            'receipt_image' => $file,
            'receipt_review_token' => $review,
            'amount' => '55.00',
        ]))->assertRedirect();

        $payment = ReservationPayment::firstOrFail();
        $this->assertSame(55.0, (float) $payment->amount);
        $this->assertSame('341897185', $payment->reference_number);
        $this->assertMatchesRegularExpression('/^[a-f0-9]{64}$/', $payment->receipt_sha256);
        $this->assertMatchesRegularExpression('/^[a-f0-9]{64}$/', $payment->reference_sha256);
        Storage::disk('local')->assertExists($payment->receipt_image_path);
        $this->assertDatabaseHas('activity_logs', ['action' => 'Receipt OCR fields corrected']);
    }

    public function test_exact_image_duplicate_is_blocked_across_reservations(): void
    {
        $first = $this->reservation();
        $second = $this->reservation();
        $file = $this->pngUpload();
        $review = $this->analyze($first, $file)->assertOk()->json('review_token');
        $this->withSession(self::ADMIN)->post(route('admin.reservations.payments.store', $first), $this->paymentFields([
            'receipt_image' => $file,
            'receipt_review_token' => $review,
        ]))->assertRedirect();

        $this->analyze($second, $this->pngUpload())
            ->assertUnprocessable()
            ->assertJsonPath('errors.receipt_image.0', fn ($message) => str_contains($message, 'Duplicate Receipt Detected'));

        $this->assertSame(1, ReservationPayment::count());
    }

    public function test_duplicate_reference_is_blocked_but_same_amount_without_reference_is_allowed(): void
    {
        $first = $this->reservation();
        $second = $this->reservation();
        $third = $this->reservation();
        $fields = [
            'payment_date' => '2026-10-03',
            'payment_type' => 'Downpayment',
            'amount' => '50.00',
            'payment_method' => 'GCash',
            'reference_number' => '341897185',
        ];
        $this->withSession(self::ADMIN)->post(route('admin.reservations.payments.store', $first), $fields)->assertRedirect();
        $this->analyze($second, $this->pngUpload('different.png', 'other-content'))
            ->assertUnprocessable()
            ->assertJsonPath('errors.reference_number.0', fn ($message) => str_contains($message, 'Duplicate Transaction Reference'));

        foreach ([$second, $third] as $reservation) {
            $this->withSession(self::ADMIN)->post(route('admin.reservations.payments.store', $reservation), [
                'payment_date' => '2026-10-03',
                'payment_type' => 'Downpayment',
                'amount' => '50.00',
                'payment_method' => 'Cash',
            ])->assertRedirect();
        }

        $this->assertSame(3, ReservationPayment::count());
    }

    public function test_analysis_endpoint_requires_an_authenticated_admin(): void
    {
        $reservation = $this->reservation();
        $this->post(route('admin.reservations.payments.receipt.analyze', $reservation), [])
            ->assertRedirect(route('admin.login'));
    }
}
