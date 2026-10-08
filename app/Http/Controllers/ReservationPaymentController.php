<?php

namespace App\Http\Controllers;

use App\Contracts\ReceiptOcrEngine;
use App\Exceptions\ReceiptOcrUnavailable;
use App\Models\ActivityLog;
use App\Models\Reservation;
use App\Models\ReservationPayment;
use App\Models\ReservationRefund;
use App\Services\ReceiptTextParser;
use Carbon\Carbon;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Symfony\Component\HttpFoundation\HeaderUtils;
use Throwable;

class ReservationPaymentController extends Controller
{
    public function index(Reservation $reservation)
    {
        $reservation->ensurePaymentLedger();
        $reservation->recalculatePaymentTotals();
        $reservation->load('payments.corrections', 'refunds', 'package');
        $transactions = $this->transactions($reservation);

        return view('admin.reservation-payments', [
            'reservation' => $reservation,
            'payments' => $reservation->payments,
            'refunds' => $reservation->refunds,
            'transactions' => $transactions,
            'financials' => $reservation->financials(),
            'types' => ReservationPayment::TYPES,
            'methods' => ReservationPayment::METHODS,
            'suggestedType' => $reservation->payments->isEmpty() ? 'Downpayment' : 'Partial Payment',
            'refundRequestKey' => (string) Str::uuid(),
        ]);
    }

    public function print(Reservation $reservation)
    {
        $reservation->ensurePaymentLedger();
        $reservation->recalculatePaymentTotals();
        $reservation->load('payments', 'refunds', 'package');
        $transactions = $this->transactions($reservation);

        return view('admin.reservation-payments-print', [
            'reservation' => $reservation,
            'payments' => $reservation->payments,
            'refunds' => $reservation->refunds,
            'transactions' => $transactions,
            'financials' => $reservation->financials(),
        ]);
    }

    public function analyzeReceipt(
        Request $request,
        Reservation $reservation,
        ReceiptOcrEngine $ocrEngine,
        ReceiptTextParser $parser,
    ): JsonResponse {
        $data = $request->validate([
            'receipt_image' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120', 'dimensions:max_width=10000,max_height=10000'],
            'payment_id' => ['nullable', 'integer'],
        ]);
        $payment = null;
        if (isset($data['payment_id'])) {
            $payment = $reservation->payments()->whereKey($data['payment_id'])->firstOrFail();
        }

        $file = $data['receipt_image'];
        $hash = $this->receiptHash($file);
        $this->assertNoDuplicateReceipt($request, $reservation, $hash, null, $payment);

        try {
            $analysis = $parser->parse($ocrEngine->recognize($file->getRealPath()));
        } catch (ReceiptOcrUnavailable) {
            $this->recordFinancialActivity($request, 'Receipt OCR unavailable', 'Local receipt OCR could not be completed; no receipt or payment was saved.', $reservation);

            return response()->json([
                'message' => 'Local receipt OCR is not installed or could not process this image. You can still record the payment manually without attaching a receipt.',
            ], 503);
        }

        if (! $analysis['detected']) {
            $this->recordFinancialActivity($request, 'Receipt rejected', 'The uploaded image was not identified as a completed payment receipt.', $reservation);

            return response()->json([
                'message' => 'Receipt Not Detected. '.$analysis['message'],
            ], 422);
        }

        $this->assertNoDuplicateReceipt(
            $request,
            $reservation,
            $hash,
            $analysis['reference_number'],
            $payment,
        );
        $this->recordFinancialActivity(
            $request,
            'Receipt OCR processed',
            'Detected '.$analysis['receipt_type'].'; extracted fields are awaiting administrator review.',
            $reservation,
        );

        $review = [
            'reservation_id' => $reservation->id,
            'payment_id' => $payment?->id,
            'receipt_sha256' => $hash,
            'analysis' => [
                'amount' => $analysis['amount'],
                'payment_method' => $analysis['payment_method'],
                'payment_date' => $analysis['payment_date'],
                'reference_number' => $analysis['reference_number'],
            ],
            'expires_at' => now()->addMinutes(30)->timestamp,
        ];

        return response()->json([
            'receipt' => $analysis,
            'review_token' => Crypt::encryptString(json_encode($review, JSON_THROW_ON_ERROR)),
        ]);
    }

    public function store(Request $request, Reservation $reservation): RedirectResponse
    {
        $data = $this->validatePayment($request);
        $receiptImage = $data['receipt_image'] ?? null;
        unset($data['receipt_image']);
        $receiptReview = $receiptImage
            ? $this->verifyReceiptReview($request, $reservation, $receiptImage, null)
            : null;
        $referenceHash = $this->referenceHash($data['reference_number'] ?? null);
        if (! $receiptImage) {
            $this->assertNoDuplicateReceipt($request, $reservation, null, $data['reference_number'] ?? null);
        }
        $newReceiptPath = null;

        try {
            $created = DB::transaction(function () use ($request, $reservation, $data, $receiptImage, $receiptReview, $referenceHash, &$newReceiptPath) {
                $reservation = Reservation::whereKey($reservation->id)->lockForUpdate()->firstOrFail();
                $reservation->ensurePaymentLedger();
                $reservation->recalculatePaymentTotals();

                if ($reservation->total_cost === null) {
                    throw ValidationException::withMessages(['amount' => 'Set the contract price before recording payments.']);
                }

                // A double-clicked Save posts the same payment twice within moments; keep only the first.
                $duplicate = $reservation->payments()
                    ->where('amount', $data['amount'])
                    ->whereDate('payment_date', $data['payment_date'])
                    ->where('payment_method', $data['payment_method'])
                    ->where('payment_type', $data['payment_type'])
                    ->where('created_at', '>=', now()->subSeconds(15))
                    ->exists();
                if ($duplicate) {
                    return false;
                }

                $this->assertWithinBalance($data['amount'], $reservation->remainingBalanceCents());

                if ($receiptImage) {
                    $newReceiptPath = $this->storeReceiptImage($receiptImage);
                    $data['receipt_sha256'] = $receiptReview['receipt_sha256'];
                    $this->recordReceiptCorrections($request, $reservation, $receiptReview['analysis'], $data);
                }
                $data['reference_sha256'] = $referenceHash;

                $payment = $reservation->payments()->create($data + [
                    'receipt_image_path' => $newReceiptPath,
                    'recorded_by_user_id' => $request->session()->get('admin_user_id'),
                    'recorded_by_name' => $request->session()->get('admin_name', 'Administrator'),
                ]);
                $reservation->recalculatePaymentTotals();

                $this->recordFinancialActivity(
                    $request,
                    'Payment recorded',
                    $this->paymentSummary($payment).' Recorded by '.$payment->recorded_by_name.'.',
                    $reservation,
                );

                if ($newReceiptPath !== null) {
                    $this->recordFinancialActivity(
                        $request,
                        'Official Receipt uploaded',
                        'Official Receipt uploaded for '.$this->peso($payment->amount).' payment. Recorded by '.$payment->recorded_by_name.'.',
                        $reservation,
                    );
                }

                return true;
            });
        } catch (QueryException $exception) {
            $this->deleteReceiptImage($newReceiptPath);
            if ($this->isUniqueConstraintViolation($exception)) {
                $this->assertNoDuplicateReceipt(
                    $request,
                    $reservation,
                    $receiptReview['receipt_sha256'] ?? null,
                    $data['reference_number'] ?? null,
                );
            }
            throw $exception;
        } catch (Throwable $exception) {
            $this->deleteReceiptImage($newReceiptPath);
            throw $exception;
        }

        return redirect()->route('admin.reservations.payments', $reservation)->with(
            'success',
            $created ? 'Payment of '.$this->peso($data['amount']).' recorded.' : 'That payment was already recorded, so the repeat submission was ignored.'
        );
    }

    public function correct(Request $request, Reservation $reservation, ReservationPayment $payment): RedirectResponse
    {
        $newReceiptPath = null;
        $receiptReview = null;

        try {
            $data = $this->validatePayment($request);
            $reason = $request->validate([
                'correction_reason' => ['required', 'string', 'min:3', 'max:1000'],
            ], [
                'correction_reason.required' => 'Enter a reason for correcting this payment.',
                'correction_reason.min' => 'The correction reason must be at least 3 characters.',
            ])['correction_reason'];
            $receiptImage = $data['receipt_image'] ?? null;
            unset($data['receipt_image'], $data['receipt_confirmed'], $data['receipt_review_token']);
            $receiptReview = $receiptImage
                ? $this->verifyReceiptReview($request, $reservation, $receiptImage, $payment)
                : null;
            $referenceHash = $this->referenceHash($data['reference_number'] ?? null);
            if (! $receiptImage) {
                $this->assertNoDuplicateReceipt($request, $reservation, null, $data['reference_number'] ?? null, $payment);
            }

            DB::transaction(function () use ($request, $reservation, $payment, $data, $reason, $receiptImage, $receiptReview, $referenceHash, &$newReceiptPath) {
                $reservation = Reservation::whereKey($reservation->id)->lockForUpdate()->firstOrFail();
                $payment = $reservation->payments()->whereKey($payment->id)->lockForUpdate()->firstOrFail();
                $reservation->ensurePaymentLedger();
                $reservation->recalculatePaymentTotals();
                $originalValues = $this->paymentSnapshot($payment);

                $financials = $reservation->financials();
                $available = ($financials['remaining_balance_cents'] ?? 0) + Reservation::toCents($payment->amount);
                $this->assertWithinBalance($data['amount'], $available, true);
                $grossAfterEdit = $financials['gross_paid_cents'] - Reservation::toCents($payment->amount) + Reservation::toCents($data['amount']);
                if ($grossAfterEdit < $financials['total_refunded_cents']) {
                    throw ValidationException::withMessages([
                        'amount' => 'The corrected payment cannot be lower than the refunds already processed.',
                    ]);
                }

                if ($receiptImage) {
                    $newReceiptPath = $this->storeReceiptImage($receiptImage);
                    $data['receipt_image_path'] = $newReceiptPath;
                    $data['receipt_sha256'] = $receiptReview['receipt_sha256'];
                }

                $data['reference_sha256'] = $referenceHash;
                $payment->update($data);
                $reservation->recalculatePaymentTotals();
                $correctedValues = $this->paymentSnapshot($payment);

                $payment->corrections()->create([
                    'reservation_id' => $reservation->id,
                    'admin_user_id' => $request->session()->get('admin_user_id'),
                    'admin_name' => $request->session()->get('admin_name', 'Unknown administrator'),
                    'admin_email' => $request->session()->get('admin_email'),
                    'reason' => trim($reason),
                    'original_values' => $originalValues,
                    'corrected_values' => $correctedValues,
                    'ocr_used' => $receiptReview !== null,
                    'receipt_validation_result' => $receiptImage ? 'passed' : 'not_replaced',
                    'duplicate_check_result' => 'passed',
                ]);

                $this->recordFinancialActivity(
                    $request,
                    'Payment Corrected',
                    $this->paymentCorrectionSummary(
                        $payment,
                        $originalValues,
                        $correctedValues,
                        trim($reason),
                        (string) $request->session()->get('admin_name', 'Unknown administrator'),
                        $receiptReview !== null,
                    ),
                    $reservation,
                );

                if ($receiptReview !== null) {
                    $this->recordReceiptCorrections($request, $reservation, $receiptReview['analysis'], $data);
                }
            });
        } catch (ValidationException $exception) {
            $this->deleteReceiptImage($newReceiptPath);

            return redirect()->route('admin.reservations.payments', $reservation)
                ->withErrors($exception->errors(), 'correctPayment')
                ->withInput($request->except('current_admin_password'))
                ->with('correcting_payment', $payment->id);
        } catch (QueryException $exception) {
            $this->deleteReceiptImage($newReceiptPath);
            if ($this->isUniqueConstraintViolation($exception)) {
                try {
                    $this->assertNoDuplicateReceipt(
                        $request,
                        $reservation,
                        $receiptReview['receipt_sha256'] ?? null,
                        $data['reference_number'] ?? null,
                        $payment,
                    );
                } catch (ValidationException $duplicateException) {
                    return redirect()->route('admin.reservations.payments', $reservation)
                        ->withErrors($duplicateException->errors(), 'correctPayment')
                        ->withInput($request->except('current_admin_password'))
                        ->with('correcting_payment', $payment->id);
                }
            }
            throw $exception;
        } catch (Throwable $exception) {
            $this->deleteReceiptImage($newReceiptPath);
            throw $exception;
        }

        return redirect()->route('admin.reservations.payments', $reservation)->with('success', 'Payment correction saved. The previous payment and receipt are preserved in correction history.');
    }

    public function receipt(Reservation $reservation, ReservationPayment $payment)
    {
        $payment = $reservation->payments()->whereKey($payment->id)->firstOrFail();
        $path = $payment->receipt_image_path;
        abort_unless($path && str_starts_with($path, 'payment-receipts/'), 404);

        $disk = Storage::disk('local');
        abort_unless($disk->exists($path), 404);
        $mimeType = $disk->mimeType($path);
        abort_unless(in_array($mimeType, ['image/jpeg', 'image/png', 'image/webp'], true), 415);

        $stream = $disk->readStream($path);
        abort_if($stream === false, 404);

        return response()->stream(
            static function () use ($stream): void {
                fpassthru($stream);
                if (is_resource($stream)) {
                    fclose($stream);
                }
            },
            200,
            [
                'Content-Type' => $mimeType,
                'Content-Disposition' => HeaderUtils::makeDisposition('inline', basename($path), Str::ascii(basename($path)) ?: 'payment-receipt'),
                'Cache-Control' => 'private, no-store, max-age=0',
                'X-Content-Type-Options' => 'nosniff',
            ],
        );
    }

    public function updateDetails(Request $request, Reservation $reservation): RedirectResponse
    {
        $data = $request->validate([
            'total_cost' => ['required', 'numeric', 'min:0', 'max:9999999999'],
            'payment_due_date' => ['nullable', 'date'],
        ], [
            'total_cost.required' => 'Enter the contract price.',
            'total_cost.min' => 'The contract price cannot be negative.',
        ]);

        DB::transaction(function () use ($request, $reservation, $data) {
            $reservation = Reservation::whereKey($reservation->id)->lockForUpdate()->firstOrFail();
            $reservation->ensurePaymentLedger();
            $reservation->recalculatePaymentTotals();

            $oldContractPriceCents = $reservation->total_cost === null
                ? null
                : Reservation::toCents($reservation->getRawOriginal('total_cost'));
            $newContractPriceCents = Reservation::toCents($data['total_cost']);
            $oldDueDate = $reservation->payment_due_date?->toDateString();
            $newDueDate = array_key_exists('payment_due_date', $data)
                ? (($data['payment_due_date'] === null)
                    ? null
                    : Carbon::parse($data['payment_due_date'])->toDateString())
                : $oldDueDate;

            $financials = $reservation->financials();
            if ($newContractPriceCents < $financials['net_paid_cents']) {
                throw ValidationException::withMessages([
                    'total_cost' => 'The contract price cannot be lower than the '.$this->peso($financials['net_paid_cents'] / 100).' net amount paid.',
                ]);
            }

            $reservation->update($data);
            $reservation->recalculatePaymentTotals();

            $contractPriceChanged = $oldContractPriceCents !== $newContractPriceCents;
            $paymentDueDateChanged = $oldDueDate !== $newDueDate;
            if ($contractPriceChanged || $paymentDueDateChanged) {
                $changes = [];

                if ($contractPriceChanged) {
                    $oldPrice = $oldContractPriceCents === null ? 'Not set' : $this->peso($oldContractPriceCents / 100);
                    $changes[] = 'Contract price: '.$oldPrice.' → '.$this->peso($newContractPriceCents / 100);
                }

                if ($paymentDueDateChanged) {
                    $oldDate = $oldDueDate === null ? 'Not set' : Carbon::parse($oldDueDate)->format('F j, Y');
                    $newDate = $newDueDate === null ? 'Not set' : Carbon::parse($newDueDate)->format('F j, Y');
                    $changes[] = 'Payment due date: '.$oldDate.' → '.$newDate;
                }

                $action = match (true) {
                    $contractPriceChanged && $paymentDueDateChanged => 'Updated contract and due date',
                    $contractPriceChanged => 'Updated contract price',
                    default => 'Updated payment due date',
                };

                $this->recordFinancialActivity($request, $action, implode("\n", $changes), $reservation);
            }
        });

        return redirect()->route('admin.reservations.payments', $reservation)->with('success', 'Contract price and payment due date saved.');
    }

    public function storeRefund(Request $request, Reservation $reservation): RedirectResponse
    {
        $data = $request->validate([
            'request_key' => ['required', 'uuid'],
            'refund_date' => ['required', 'date', 'before_or_equal:today'],
            'refund_amount' => ['required', 'numeric', 'gt:0', 'max:9999999999', 'decimal:0,2'],
            'refund_method' => ['required', Rule::in(ReservationPayment::METHODS)],
            'reason' => ['nullable', 'string', 'max:1000'],
        ], [
            'refund_date.required' => 'Choose the refund date.',
            'refund_date.before_or_equal' => 'The refund date cannot be in the future.',
            'refund_amount.required' => 'Enter the refund amount.',
            'refund_amount.gt' => 'The refund amount must be greater than ₱0.00.',
            'refund_amount.decimal' => 'The refund amount can have at most two decimal places.',
            'refund_method.required' => 'Choose the refund method.',
            'refund_method.in' => 'Choose Cash, GCash, Bank Transfer, or Other.',
        ]);
        $data['refund_amount'] = round((float) $data['refund_amount'], 2);

        $created = DB::transaction(function () use ($request, $reservation, $data): bool {
            $reservation = Reservation::whereKey($reservation->id)->lockForUpdate()->firstOrFail();
            $reservation->ensurePaymentLedger();

            $duplicate = ReservationRefund::where('request_key', $data['request_key'])->first();
            if ($duplicate) {
                if ($duplicate->reservation_id !== $reservation->id) {
                    throw ValidationException::withMessages([
                        'refund_amount' => 'This refund submission has already been used. Refresh the page and try again.',
                    ]);
                }

                return false;
            }

            $financials = $reservation->financials();
            $amountCents = Reservation::toCents($data['refund_amount']);
            if ($amountCents > $financials['net_paid_cents']) {
                throw ValidationException::withMessages([
                    'refund_amount' => 'Refund cannot exceed the total amount paid.',
                ]);
            }

            $refund = $reservation->refunds()->create([
                'refund_date' => $data['refund_date'],
                'amount' => $data['refund_amount'],
                'refund_method' => $data['refund_method'],
                'reason' => $data['reason'] ?? null,
                'status' => 'completed',
                'request_key' => $data['request_key'],
                'recorded_by_user_id' => $request->session()->get('admin_user_id'),
                'recorded_by_name' => $request->session()->get('admin_name', 'Administrator'),
            ]);

            $reservation->recalculatePaymentTotals();

            $this->recordFinancialActivity(
                $request,
                'Refund recorded',
                'Amount: '.$this->peso($refund->amount)
                    ."\nRefund method: ".$refund->refund_method
                    ."\nRefund date: ".$refund->refund_date->format('F j, Y')
                    .($refund->reason ? "\nReason: ".$refund->reason : '')
                    ."\nRecorded by ".$refund->recorded_by_name.'.',
                $reservation,
            );

            return true;
        });

        return redirect()->route('admin.reservations.payments', $reservation)->with(
            'success',
            $created
                ? 'Refund of '.$this->peso($data['refund_amount']).' processed.'
                : 'That refund submission was already processed.',
        );
    }

    private function validatePayment(Request $request): array
    {
        $data = $request->validate([
            'payment_date' => ['required', 'date', 'before_or_equal:today'],
            'payment_type' => ['required', Rule::in(ReservationPayment::TYPES)],
            'amount' => ['required', 'numeric', 'gt:0', 'max:9999999999', 'decimal:0,2'],
            'payment_method' => ['required', Rule::in(ReservationPayment::METHODS)],
            'reference_number' => ['nullable', 'string', 'max:100'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'receipt_image' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120', 'dimensions:max_width=10000,max_height=10000'],
            'receipt_confirmed' => ['sometimes', 'accepted'],
            'receipt_review_token' => ['nullable', 'string', 'max:10000'],
        ], [
            'payment_date.required' => 'Choose the payment date.',
            'payment_date.before_or_equal' => 'The payment date cannot be in the future.',
            'payment_type.required' => 'Choose the payment type.',
            'amount.required' => 'Enter the payment amount.',
            'amount.numeric' => 'The amount must be a number.',
            'amount.gt' => 'The amount must be greater than ₱0.00.',
            'amount.decimal' => 'The amount can have at most two decimal places.',
            'payment_method.required' => 'Choose how the customer paid.',
            'payment_method.in' => 'Choose Cash, GCash, Maya, Bank Transfer, or Other.',
        ]);
        $data['amount'] = round((float) $data['amount'], 2);

        return $data;
    }

    private function verifyReceiptReview(
        Request $request,
        Reservation $reservation,
        UploadedFile $file,
        ?ReservationPayment $payment,
    ): array {
        if (! $request->boolean('receipt_confirmed')) {
            throw ValidationException::withMessages([
                'receipt_image' => 'Review the receipt details and confirm them before recording a payment.',
            ]);
        }

        $token = $request->input('receipt_review_token');
        if (! is_string($token) || $token === '') {
            throw ValidationException::withMessages([
                'receipt_image' => 'Analyze this receipt before recording it.',
            ]);
        }

        try {
            $review = json_decode(Crypt::decryptString($token), true, 512, JSON_THROW_ON_ERROR);
        } catch (DecryptException|\JsonException) {
            throw ValidationException::withMessages([
                'receipt_image' => 'Receipt review expired or is invalid. Analyze the image again.',
            ]);
        }

        $hash = $this->receiptHash($file);
        if (($review['reservation_id'] ?? null) !== $reservation->id
            || ($review['payment_id'] ?? null) !== $payment?->id
            || ($review['receipt_sha256'] ?? null) !== $hash
            || ! is_int($review['expires_at'] ?? null)
            || $review['expires_at'] < now()->timestamp
            || ! is_array($review['analysis'] ?? null)) {
            throw ValidationException::withMessages([
                'receipt_image' => 'Receipt review expired or the image changed. Analyze the current image again.',
            ]);
        }

        $this->assertNoDuplicateReceipt(
            $request,
            $reservation,
            $hash,
            $request->input('reference_number'),
            $payment,
        );

        return [
            'receipt_sha256' => $hash,
            'analysis' => $review['analysis'],
        ];
    }

    private function receiptHash(UploadedFile $file): string
    {
        $path = $file->getRealPath();
        $hash = is_string($path) ? hash_file('sha256', $path) : false;
        if (! is_string($hash)) {
            throw ValidationException::withMessages([
                'receipt_image' => 'The uploaded receipt image could not be read.',
            ]);
        }

        return $hash;
    }

    private function referenceHash(?string $reference): ?string
    {
        if (! is_string($reference) || trim($reference) === '') {
            return null;
        }

        $normalized = preg_replace('/[^A-Z0-9]/', '', mb_strtoupper(trim($reference)));

        return $normalized === '' ? null : hash('sha256', $normalized);
    }

    private function assertNoDuplicateReceipt(
        Request $request,
        Reservation $reservation,
        ?string $receiptHash,
        ?string $reference,
        ?ReservationPayment $except = null,
    ): void {
        $duplicate = null;
        $message = null;
        $errorField = 'reference_number';
        if ($receiptHash !== null) {
            $duplicate = ReservationPayment::with('reservation')
                ->where('receipt_sha256', $receiptHash)
                ->when($except, fn ($query) => $query->where('id', '<>', $except->id))
                ->first();
            if ($duplicate) {
                $errorField = 'receipt_image';
                $message = 'Duplicate Receipt Detected. This exact receipt image has already been recorded for '
                    .$this->reservationLabel($duplicate->reservation).'. Amount: '.$this->peso($duplicate->amount)
                    .'. Payment method: '.$duplicate->payment_method.'.';
                if ($duplicate->reference_number) {
                    $message .= ' Reference Number: '.$duplicate->reference_number.'.';
                }
            }
        }

        $referenceHash = $this->referenceHash($reference);
        if (! $duplicate && $referenceHash !== null) {
            $duplicate = ReservationPayment::with('reservation')
                ->where('reference_sha256', $referenceHash)
                ->when($except, fn ($query) => $query->where('id', '<>', $except->id))
                ->first();
            if ($duplicate) {
                $message = 'Duplicate Transaction Reference. This reference number has already been recorded for '
                    .$this->reservationLabel($duplicate->reservation).'.';
            }
        }

        if ($duplicate) {
            $this->recordFinancialActivity($request, 'Duplicate receipt blocked', 'A duplicate receipt or transaction reference was blocked.', $reservation);
            throw ValidationException::withMessages([
                $errorField => $message,
            ]);
        }
    }

    private function reservationLabel(?Reservation $reservation): string
    {
        return $reservation
            ? 'Reservation '.($reservation->reservation_code ?: '#'.$reservation->id)
            : 'another reservation';
    }

    private function isUniqueConstraintViolation(QueryException $exception): bool
    {
        return $exception->getCode() === '23000'
            || str_contains(strtolower($exception->getMessage()), 'unique constraint');
    }

    private function recordReceiptCorrections(Request $request, Reservation $reservation, array $extracted, array $submitted): void
    {
        $corrections = [];
        if (isset($extracted['amount'])
            && Reservation::toCents((float) $extracted['amount']) !== Reservation::toCents((float) ($submitted['amount'] ?? 0))) {
            $corrections[] = 'amount';
        }
        foreach (['payment_method', 'payment_date'] as $field) {
            if (! empty($extracted[$field]) && ($submitted[$field] ?? null) !== $extracted[$field]) {
                $corrections[] = $field;
            }
        }
        if (! empty($extracted['reference_number'])
            && $this->referenceHash($extracted['reference_number']) !== $this->referenceHash($submitted['reference_number'] ?? null)) {
            $corrections[] = 'reference number';
        }

        if ($corrections !== []) {
            $this->recordFinancialActivity(
                $request,
                'Receipt OCR fields corrected',
                'Administrator reviewed and corrected extracted fields: '.implode(', ', $corrections).'.',
                $reservation,
            );
        }
    }

    private function storeReceiptImage(UploadedFile $file): string
    {
        $path = $file->store('payment-receipts', 'local');
        if (! is_string($path) || $path === '') {
            throw new RuntimeException('The official receipt image could not be stored.');
        }

        return $path;
    }

    private function deleteReceiptImage(?string $path): void
    {
        if (! $path || ! str_starts_with($path, 'payment-receipts/')) {
            return;
        }

        if (Storage::disk('local')->exists($path) && ! Storage::disk('local')->delete($path)) {
            report(new RuntimeException('The official receipt image could not be deleted: '.$path));
        }
    }

    private function assertWithinBalance(float $amount, ?int $availableCents, bool $editing = false): void
    {
        if ($availableCents === null) {
            throw ValidationException::withMessages(['amount' => 'Set the contract price before recording payments.']);
        }

        if (Reservation::toCents($amount) > $availableCents) {
            throw ValidationException::withMessages([
                'amount' => $editing
                    ? 'This payment can be at most '.$this->peso($availableCents / 100).' (the remaining balance plus its current amount).'
                    : ($availableCents === 0
                    ? 'This booking is already fully paid.'
                    : 'The amount cannot exceed the remaining balance of '.$this->peso($availableCents / 100).'.'),
            ]);
        }
    }

    private function peso(float|int|null $amount): string
    {
        return '₱'.number_format((float) $amount, 2);
    }

    private function paymentSummary(ReservationPayment $payment): string
    {
        return 'Amount: '.$this->peso($payment->amount)
            ."\nPayment method: ".$payment->payment_method
            ."\nPayment date: ".$payment->payment_date->format('F j, Y')
            ."\nPayment type: ".$payment->payment_type;
    }

    private function paymentSnapshot(ReservationPayment $payment): array
    {
        return [
            'payment_type' => $payment->payment_type,
            'amount' => number_format((float) $payment->amount, 2, '.', ''),
            'payment_method' => $payment->payment_method,
            'payment_date' => $payment->payment_date->toDateString(),
            'reference_number' => $payment->reference_number,
            'notes' => $payment->notes,
            'receipt_image_path' => $payment->receipt_image_path,
            'receipt_sha256' => $payment->receipt_sha256,
        ];
    }

    private function paymentCorrectionSummary(
        ReservationPayment $payment,
        array $original,
        array $corrected,
        string $reason,
        string $adminName,
        bool $ocrUsed,
    ): string {
        $formatAmount = fn (string $amount): string => $this->peso((float) $amount);
        $formatReceipt = static fn (?string $path): string => $path ? basename($path) : 'No receipt';
        $lines = [
            'Payment ID: #'.$payment->id,
            'Administrator: '.$adminName,
            'Reason: '.$reason,
            'Payment type: '.$original['payment_type'].' → '.$corrected['payment_type'],
            'Amount: '.$formatAmount($original['amount']).' → '.$formatAmount($corrected['amount']),
            'Payment method: '.$original['payment_method'].' → '.$corrected['payment_method'],
            'Payment date: '.$original['payment_date'].' → '.$corrected['payment_date'],
            'Reference number: '.($original['reference_number'] ?: 'None').' → '.($corrected['reference_number'] ?: 'None'),
            'Receipt: '.$formatReceipt($original['receipt_image_path']).' → '.$formatReceipt($corrected['receipt_image_path']),
            'OCR: '.($ocrUsed ? 'Used; administrator review required and completed' : 'Not used'),
            'Receipt validation: '.($ocrUsed ? 'Passed' : 'No replacement receipt'),
            'Duplicate detection: Passed',
        ];

        return implode("\n", $lines);
    }

    private function recordFinancialActivity(
        Request $request,
        string $action,
        string $description,
        Reservation $reservation,
    ): void {
        ActivityLog::create([
            'user_id' => $request->session()->get('admin_user_id'),
            'actor_name' => $request->session()->get('admin_name', 'Unknown administrator'),
            'actor_email' => $request->session()->get('admin_email'),
            'actor_role' => $request->session()->get('admin_role', 'limited'),
            'action' => $action,
            'method' => $request->method(),
            'ip_address' => $request->ip(),
            'activity_date' => now()->toDateString(),
            'activity_time' => now()->toTimeString(),
            'description' => $description."\nReservation: ".($reservation->reservation_code ?: '#'.$reservation->id).'.',
        ]);
    }

    private function transactions(Reservation $reservation)
    {
        $payments = $reservation->payments->map(fn (ReservationPayment $payment) => (object) [
            'kind' => 'payment',
            'date' => $payment->payment_date,
            'type' => $payment->payment_type,
            'amount' => $payment->amount,
            'method' => $payment->payment_method,
            'notes' => $payment->notes,
            'recorded_by_name' => $payment->recorded_by_name,
            'created_at' => $payment->created_at,
            'id' => $payment->id,
            'payment' => $payment,
        ]);

        $refunds = $reservation->refunds->map(fn (ReservationRefund $refund) => (object) [
            'kind' => 'refund',
            'date' => $refund->refund_date,
            'type' => 'Refund',
            'amount' => $refund->amount,
            'method' => $refund->refund_method,
            'notes' => $refund->reason,
            'recorded_by_name' => $refund->recorded_by_name,
            'created_at' => $refund->created_at,
            'id' => $refund->id,
            'payment' => null,
        ]);

        return $payments->concat($refunds)->sortBy([
            ['date', 'asc'],
            ['created_at', 'asc'],
            ['id', 'asc'],
        ])->values();
    }
}
