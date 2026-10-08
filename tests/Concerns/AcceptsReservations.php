<?php

namespace Tests\Concerns;

use App\Contracts\ReceiptOcrEngine;
use App\Models\Reservation;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

trait AcceptsReservations
{
    private bool $acceptanceStorageFaked = false;

    protected function postReservationAcceptance(Reservation $reservation, array $admin, array $overrides = [], ?string $ocrText = null)
    {
        if (! $this->acceptanceStorageFaked) {
            Storage::fake('local');
            $this->acceptanceStorageFaked = true;
        }

        $this->app->instance(ReceiptOcrEngine::class, new class($ocrText) implements ReceiptOcrEngine
        {
            public function __construct(private ?string $text) {}

            public function recognize(string $imagePath): string
            {
                return $this->text ?? "Generika Chrysanthemum A\nPaid via GCash\nAmount 50.00\nTotal 50.00\nDate Oct 03, 2026 7:56 PM\nReference No. 341897185\nPayment successful";
            }
        });

        $png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=', true);
        $file = UploadedFile::fake()->createWithContent('acceptance-receipt.png', $png.random_bytes(8));
        $analysis = $this->withSession($admin)->postJson(
            route('admin.reservations.payments.receipt.analyze', $reservation),
            ['receipt_image' => $file],
        );
        $analysis->assertOk();

        return $this->withSession($admin)->post(
            route('admin.reservations.accept', $reservation),
            array_merge([
                'total_cost' => $reservation->total_cost ?? 1000,
                'payment_date' => now()->toDateString(),
                'payment_type' => 'Downpayment',
                'amount' => 100,
                'payment_method' => 'GCash',
                'reference_number' => '',
                'receipt_image' => $file,
                'receipt_confirmed' => '1',
                'receipt_review_token' => $analysis->json('review_token'),
            ], $overrides),
        );
    }
}
