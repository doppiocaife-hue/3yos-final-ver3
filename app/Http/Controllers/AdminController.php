<?php

namespace App\Http\Controllers;

use App\Mail\InquiryReplyMail;
use App\Mail\ReservationAcceptedMail;
use App\Mail\ReservationCancelledMail;
use App\Models\Inquiry;
use App\Models\Package;
use App\Models\Reservation;
use App\Models\ReservationPayment;
use App\Models\ReservationRefund;
use App\Models\ReservationStatusNotification;
use App\Models\Service;
use App\Services\ReservationFinancialService;
use Illuminate\Database\Eloquent\Builder;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class AdminController extends Controller
{
    public function index()
    {
        $reservationCount = Reservation::count();
        $inquiryCount = Inquiry::count();
        $serviceCount = Service::count();
        $packageCount = Package::count();
        $calendarEvents = Reservation::query()
            ->whereIn('status', ['confirmed', 'completed', 'cancelled'])
            ->orderBy('event_date')
            ->get(['reservation_code', 'full_name', 'event_type', 'event_date', 'event_time', 'venue', 'status'])
            ->map(fn (Reservation $reservation) => [
                'code' => $reservation->reservation_code,
                'name' => $reservation->full_name,
                'eventType' => $reservation->event_type,
                'date' => $reservation->event_date,
                'time' => $reservation->event_time,
                'venue' => $reservation->venue,
                'status' => $reservation->status,
            ])
            ->values();

        return view('admin.dashboard', compact('reservationCount', 'inquiryCount', 'serviceCount', 'packageCount', 'calendarEvents'));
    }

    public function reservations(Request $request)
    {
        $status = $request->input('status');
        $paymentStatus = $request->input('payment_status');
        $search = $request->input('search');
        $dateFrom = $request->input('date_from');
        $dateTo = $request->input('date_to');

        $query = Reservation::with('client', 'package', 'payments', 'refunds')->latest();

        if ($status && in_array($status, ['pending', 'confirmed', 'completed', 'cancelled'], true)) {
            $query->where('status', $status);
        }

        if ($paymentStatus && in_array($paymentStatus, ['Unpaid', 'Downpayment', 'Partial Payment', 'Fully Paid'], true)) {
            $query->where('payment_status', $paymentStatus);
        }

        if ($search !== null && trim($search) !== '') {
            $this->applyReservationSearch($query, $search);
        }

        if ($dateFrom) {
            $query->whereDate('event_date', '>=', $dateFrom);
        }

        if ($dateTo) {
            $query->whereDate('event_date', '<=', $dateTo);
        }

        $matchingReservationCount = (clone $query)->count();
        $perPage = 10;
        $lastPage = max(1, (int) ceil($matchingReservationCount / $perPage));

        if ($request->integer('page', 1) > $lastPage) {
            return redirect()->route('admin.reservations', array_merge(
                $request->query(),
                ['page' => $lastPage],
            ));
        }

        $reservations = $query->paginate($perPage)->withQueryString();
        $packages = Package::orderBy('price')->get(['id', 'name', 'price']);
        $customerCount = Reservation::query()->distinct('email')->count('email');
        $pendingCount = Reservation::query()->where('status', 'pending')->count();
        $acceptedCount = Reservation::query()->where('status', 'confirmed')->count();
        $cancelledCount = Reservation::query()->where('status', 'cancelled')->count();

        return view('admin.reservations', compact(
            'reservations',
            'matchingReservationCount',
            'packages',
            'customerCount',
            'pendingCount',
            'acceptedCount',
            'cancelledCount',
            'status',
            'paymentStatus',
            'search',
            'dateFrom',
            'dateTo',
        ))->with([
            'filterStatus' => $status,
            'filterPaymentStatus' => $paymentStatus,
            'search' => $search,
            'dateFrom' => $dateFrom,
            'dateTo' => $dateTo,
        ]);
    }

    public function exportReservationsCsv(Request $request, ?ReservationFinancialService $financialService = null)
    {
        $financialService ??= app(ReservationFinancialService::class);
        $query = Reservation::with('payments', 'refunds')->latest();

        $status = $request->input('status');
        $paymentStatus = $request->input('payment_status');
        $search = trim((string) $request->input('search', ''));
        $dateFrom = $request->input('date_from');
        $dateTo = $request->input('date_to');

        if ($status && in_array($status, ['pending', 'confirmed', 'completed', 'cancelled'], true)) {
            $query->where('status', $status);
        }

        if ($paymentStatus && in_array($paymentStatus, ['Unpaid', 'Downpayment', 'Partial Payment', 'Fully Paid'], true)) {
            $query->where('payment_status', $paymentStatus);
        }

        if ($search !== '') {
            $this->applyReservationSearch($query, $search);
        }

        if ($dateFrom) {
            $query->whereDate('event_date', '>=', $dateFrom);
        }

        if ($dateTo) {
            $query->whereDate('event_date', '<=', $dateTo);
        }

        $reservations = $query->get();

        $handle = fopen('php://temp', 'r+');
        fputcsv($handle, ['Reservation Code', 'Customer Name', 'Email', 'Contact Number', 'Event Type', 'Event Date', 'Venue', 'Status', 'Payment Status', 'Contract Price', 'Gross Paid', 'Refunded', 'Net Paid', 'Balance']);

        foreach ($reservations as $reservation) {
            $amounts = $financialService->amounts($reservation);
            fputcsv($handle, [
                $reservation->reservation_code ?? '',
                $reservation->full_name ?? '',
                $reservation->email ?? '',
                $reservation->contact_number ?? '',
                $reservation->event_type ?? '',
                $reservation->event_date ? Carbon::parse($reservation->event_date)->format('Y-m-d') : '',
                $reservation->venue ?? '',
                $reservation->status ?? '',
                $reservation->payment_status ?? '',
                (string) ($reservation->total_cost ?? 0),
                number_format($amounts['gross_paid'], 2, '.', ''),
                number_format($amounts['total_refunded'], 2, '.', ''),
                number_format($amounts['net_paid'], 2, '.', ''),
                number_format($amounts['remaining_balance'] ?? 0, 2, '.', ''),
            ]);
        }

        rewind($handle);
        $csv = stream_get_contents($handle);
        fclose($handle);

        $filename = 'reservations-'.now()->format('YmdHis').'.csv';

        return response($csv ?: '', 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
        ]);
    }

    public function inquiries()
    {
        $inquiries = Inquiry::latest()->get();

        return view('admin.inquiries', compact('inquiries'));
    }

    public function showInquiry(Inquiry $inquiry)
    {
        if ($inquiry->status === 'new') {
            $inquiry->update(['status' => 'in_progress']);
        }

        return view('admin.inquiry-show', compact('inquiry'));
    }

    public function replyToInquiry(Request $request, Inquiry $inquiry)
    {
        // Step 1: validate the reply is not empty.
        $data = $request->validate(['reply' => ['required', 'string', 'max:5000']]);

        $mailSent = false;
        $mailError = null;

        // Step 2: send the email first. The status only changes if this succeeds.
        try {
            Mail::to($inquiry->email, $inquiry->full_name)->send(new InquiryReplyMail(
                $inquiry->full_name,
                $data['reply'],
                'Re: '.$inquiry->subject,
            ));
            $mailSent = ! in_array(config('mail.default'), ['log', 'array'], true);
        } catch (\Throwable $exception) {
            $mailError = $exception;
            report($exception);
        }

        if (! $mailSent) {
            // The reply text is kept so the admin doesn't lose what they typed, but the status stays untouched.
            $inquiry->update(['admin_reply' => $data['reply']]);

            if (in_array(config('mail.default'), ['log', 'array'], true) && $mailError === null) {
                return redirect()->route('admin.inquiries.show', $inquiry)->with(
                    'error',
                    'Failed to send reply. The inquiry status was not changed. Email delivery is disabled because MAIL_MAILER is set to '.config('mail.default').'.'
                );
            }

            return back()->with(
                'error',
                'Failed to send reply. The inquiry status was not changed. Details: '.($mailError?->getMessage() ?? 'Unknown mail error.')
            );
        }

        // Step 3: the email was sent successfully — now mark the inquiry as responded.
        try {
            $inquiry->update(['admin_reply' => $data['reply'], 'replied_at' => now(), 'status' => 'responded']);
        } catch (\Throwable $exception) {
            report($exception);

            return redirect()->route('admin.inquiries.show', $inquiry)->with(
                'error',
                'Reply was sent to '.$inquiry->email.', but the inquiry status could not be updated. Please refresh and update it manually.'
            );
        }

        // Step 4: confirm both the email and the status change.
        return redirect()->route('admin.inquiries.show', $inquiry)->with('success', 'Reply sent successfully. Inquiry marked as Responded.');
    }

    public function destroyInquiry(Inquiry $inquiry)
    {
        $inquiry->delete();

        return redirect()->route('admin.inquiries')->with('success', 'Inquiry deleted.');
    }

    public function analytics(ReservationFinancialService $financialService)
    {
        $reservations = Reservation::with('payments', 'refunds', 'package:id,name')->get();
        $financials = $reservations->mapWithKeys(fn (Reservation $reservation) => [
            $reservation->id => $financialService->calculate($reservation),
        ]);

        $cents = fn (string $key) => (int) $financials->sum($key);
        $totals = [
            'reservations' => $reservations->count(),
            'paid' => $cents('gross_paid_cents') / 100,
            'refunded' => $cents('total_refunded_cents') / 100,
            'net' => $cents('net_paid_cents') / 100,
            'outstanding' => (int) $financials->sum(fn (array $f) => $f['remaining_balance_cents'] ?? 0) / 100,
            'refunded_reservations' => $financials->filter(fn (array $f) => $f['total_refunded_cents'] > 0)->count(),
        ];

        $statusCounts = collect(['pending', 'confirmed', 'completed', 'cancelled'])
            ->mapWithKeys(fn ($status) => [$status => $reservations->where('status', $status)->count()]);

        $topPackages = $reservations->filter(fn (Reservation $r) => $r->package)
            ->groupBy('package_id')
            ->map(fn ($group) => (object) [
                'name' => $group->first()->package->name,
                'total' => $group->count(),
                'revenue' => $group->sum(fn (Reservation $r) => $financials[$r->id]['net_paid_cents']) / 100,
            ])
            ->sortByDesc('total')
            ->take(5)
            ->values();

        $months = collect(range(5, 0))->map(fn ($ago) => now()->startOfMonth()->subMonths($ago));
        $start = $months->first()->toDateString();
        $payments = ReservationPayment::with('reservation:id,full_name')->whereDate('payment_date', '>=', $start)->get();
        $refunds = ReservationRefund::with('reservation:id,full_name')->where('status', 'completed')->whereDate('refund_date', '>=', $start)->get();
        $monthly = $months->map(function ($month) use ($reservations, $payments, $refunds) {
            $key = $month->format('Y-m');
            $paid = $payments->filter(fn ($p) => $p->payment_date->format('Y-m') === $key)->sum(fn ($p) => Reservation::toCents($p->amount));
            $refunded = $refunds->filter(fn ($r) => $r->refund_date->format('Y-m') === $key)->sum(fn ($r) => Reservation::toCents($r->amount));

            return (object) [
                'label' => $month->format('F Y'),
                'reservations' => $reservations->filter(fn ($r) => $r->created_at->format('Y-m') === $key)->count(),
                'paid' => $paid / 100,
                'refunded' => $refunded / 100,
                'net' => ($paid - $refunded) / 100,
            ];
        });

        $recentReservations = $reservations->sortByDesc('created_at')->take(5)->values();
        $recentPayments = ReservationPayment::with('reservation:id,full_name')->latest('payment_date')->latest('id')->take(5)->get();
        $recentRefunds = ReservationRefund::with('reservation:id,full_name')->where('status', 'completed')->latest('refund_date')->latest('id')->take(5)->get();

        return view('admin.analytics', compact('totals', 'statusCounts', 'topPackages', 'monthly', 'recentReservations', 'recentPayments', 'recentRefunds'));
    }

    public function updateReservationStatus(Request $request, Reservation $reservation)
    {
        $data = $request->validate([
            'status' => ['sometimes', 'required', 'in:pending,confirmed,completed,cancelled'],
            'payment_status' => ['sometimes', 'nullable', 'in:Unpaid,Downpayment,Partial Payment,Fully Paid'],
            'payment_type' => ['sometimes', 'nullable', 'in:Unpaid,Downpayment,Partial Payment,Final Payment,Full Payment'],
            'total_cost' => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'amount_paid' => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'event_date' => ['sometimes', 'required', 'date'],
            'event_time' => ['sometimes', 'required', 'string', 'max:20'],
            'package_id' => ['sometimes', 'required', 'exists:packages,id'],
        ]);

        $hasScheduleUpdate = $request->has('event_date') || $request->has('event_time') || $request->has('package_id');
        if ($hasScheduleUpdate && $reservation->status !== 'confirmed') {
            return back()->withInput()->withErrors(['schedule' => 'Only accepted reservations can have their schedule or package edited.']);
        }

        $hasPaymentUpdate = $request->has('amount_paid') || $request->has('payment_type') || $request->has('payment_status');
        $hasTotalCostUpdate = $request->has('total_cost');
        if ($hasPaymentUpdate && ! array_key_exists('total_cost', $data) && $reservation->total_cost === null) {
            return back()->withInput()->withErrors(['total_cost' => 'Enter the contract price before saving payment details.']);
        }

        if ($hasTotalCostUpdate && $data['total_cost'] === null) {
            return back()->withInput()->withErrors(['total_cost' => 'Enter the contract price before saving payment details.']);
        }

        // Payment totals and status are derived from the payment history, never written directly.
        $requestedPaid = $request->has('amount_paid') ? round((float) ($data['amount_paid'] ?? 0), 2) : null;
        unset($data['payment_status'], $data['payment_type'], $data['amount_paid']);

        $statusTransition = null;

        DB::transaction(function () use ($request, $reservation, $data, $requestedPaid, &$statusTransition): void {
            // Lock the row so a concurrent save can't race past this status comparison.
            $originalStatus = Reservation::whereKey($reservation->id)->lockForUpdate()->value('status');

            if (array_key_exists('status', $data) && $data['status'] === 'confirmed') {
                $eventDate = $data['event_date'] ?? $reservation->event_date;
                $acceptedCount = Reservation::whereDate('event_date', $eventDate)
                    ->where('status', 'confirmed')
                    ->where('id', '!=', $reservation->id)
                    ->count();

                if ($acceptedCount >= Reservation::MAX_ACCEPTED_BOOKINGS_PER_DATE) {
                    throw ValidationException::withMessages([
                        'status' => 'Maximum accepted bookings for this date has been reached. Only '.Reservation::MAX_ACCEPTED_BOOKINGS_PER_DATE.' accepted bookings are allowed per day.',
                    ]);
                }
            }

            $reservation->ensurePaymentLedger();
            $reservation->recalculatePaymentTotals();
            $paidCents = Reservation::toCents($reservation->amount_paid);
            $targetPaidCents = $requestedPaid === null ? $paidCents : Reservation::toCents($requestedPaid);
            $contractKnown = array_key_exists('total_cost', $data) || $reservation->total_cost !== null;
            $contractCents = Reservation::toCents($data['total_cost'] ?? $reservation->total_cost);

            if ($targetPaidCents < $paidCents) {
                throw ValidationException::withMessages(['amount_paid' => 'To lower the amount paid, edit or delete entries in the payment history.']);
            }
            if ($contractKnown && $targetPaidCents > $contractCents) {
                throw ValidationException::withMessages([
                    array_key_exists('total_cost', $data) && $requestedPaid === null ? 'total_cost' : 'amount_paid' => 'The contract price cannot be lower than the total amount paid (₱'.number_format($targetPaidCents / 100, 2).').',
                ]);
            }

            $reservation->update($data);

            if (array_key_exists('status', $data) && $data['status'] !== $originalStatus && in_array($data['status'], ['confirmed', 'cancelled'], true)) {
                $statusTransition = $data['status'];
            }

            // Older forms post a running total; record the increase as a payment history entry.
            if ($targetPaidCents > $paidCents) {
                $isFirst = ! $reservation->payments()->exists();
                $reachesContract = $contractKnown && $targetPaidCents >= $contractCents;
                $reservation->payments()->create([
                    'payment_date' => now()->toDateString(),
                    'payment_type' => $reachesContract ? ($isFirst ? 'Full Payment' : 'Final Payment') : ($isFirst ? 'Downpayment' : 'Partial Payment'),
                    'amount' => ($targetPaidCents - $paidCents) / 100,
                    'payment_method' => 'Other',
                    'notes' => 'Recorded from the reservations list.',
                    'recorded_by_user_id' => $request->hasSession() ? $request->session()->get('admin_user_id') : null,
                    'recorded_by_name' => $request->hasSession() ? $request->session()->get('admin_name', 'Administrator') : 'Administrator',
                ]);
            }

            $reservation->recalculatePaymentTotals();
        });

        if ($statusTransition !== null) {
            return back()->with('success', $this->sendStatusNotification($reservation, $statusTransition));
        }

        return back()->with('success', 'Reservation saved successfully.');
    }

    /**
     * Send the accepted/cancelled notification email, log the outcome, and return the flash message.
     */
    private function sendStatusNotification(Reservation $reservation, string $status): string
    {
        $notificationType = $status === 'confirmed' ? 'accepted' : 'cancelled';
        $savedMessage = $status === 'confirmed' ? 'Reservation accepted' : 'Reservation cancelled';

        if (! $reservation->email) {
            ReservationStatusNotification::create([
                'reservation_id' => $reservation->id,
                'recipient_email' => '',
                'notification_type' => $notificationType,
                'status' => 'failed',
                'error_message' => 'Reservation has no email address on file.',
            ]);

            return $savedMessage.', but the notification email could not be sent.';
        }

        try {
            Mail::to($reservation->email, $reservation->full_name)->send(
                $notificationType === 'accepted'
                    ? new ReservationAcceptedMail($reservation)
                    : new ReservationCancelledMail($reservation)
            );

            ReservationStatusNotification::create([
                'reservation_id' => $reservation->id,
                'recipient_email' => $reservation->email,
                'notification_type' => $notificationType,
                'status' => 'sent',
                'sent_at' => now(),
            ]);

            return $savedMessage.' and notification email sent.';
        } catch (\Throwable $e) {
            report($e);

            ReservationStatusNotification::create([
                'reservation_id' => $reservation->id,
                'recipient_email' => $reservation->email,
                'notification_type' => $notificationType,
                'status' => 'failed',
                'error_message' => $e->getMessage(),
            ]);

            return $savedMessage.', but the notification email could not be sent.';
        }
    }

    public function uploadReservationContract(Request $request, Reservation $reservation)
    {
        $data = $request->validate([
            'service_contract' => ['required', 'array', 'min:1', 'max:10'],
            'service_contract.*' => ['image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
        ]);

        $paths = $reservation->service_contracts ?? [];
        foreach ($data['service_contract'] as $file) {
            $paths[] = $file->store('service-contracts', 'public');
        }
        $reservation->update(['service_contracts' => $paths]);

        return back()->with('success', count($data['service_contract']).' contract image(s) uploaded.');
    }

    public function deleteReservationContract(Request $request, Reservation $reservation, int $contract)
    {
        $files = $reservation->contractFiles();
        abort_unless(isset($files[$contract]), 404);

        Storage::disk('public')->delete($files[$contract]);
        $files = array_values(array_diff($files, [$files[$contract]]));

        $reservation->update([
            'service_contract' => null,
            'service_contracts' => $files,
        ]);

        return back()->with('success', 'Contract image deleted.');
    }

    private function applyReservationSearch(Builder $query, string $search): void
    {
        $term = str($search)->lower()->toString();
        $pattern = '%'.$term.'%';

        $query->where(function (Builder $matches) use ($pattern, $term): void {
            $matches->whereRaw('LOWER(COALESCE(reservation_code, \'\')) LIKE ?', [$pattern])
                ->orWhereRaw('LOWER(CAST(id AS CHAR)) LIKE ?', [$pattern])
                ->orWhereRaw('LOWER(COALESCE(full_name, \'\')) LIKE ?', [$pattern])
                ->orWhereRaw('LOWER(COALESCE(email, \'\')) LIKE ?', [$pattern])
                ->orWhereRaw('LOWER(COALESCE(contact_number, \'\')) LIKE ?', [$pattern])
                ->orWhereRaw('LOWER(COALESCE(event_type, \'\')) LIKE ?', [$pattern])
                ->orWhereRaw('LOWER(COALESCE(venue, \'\')) LIKE ?', [$pattern])
                ->orWhereRaw('LOWER(COALESCE(address, \'\')) LIKE ?', [$pattern])
                ->orWhereRaw('LOWER(COALESCE(status, \'\')) LIKE ?', [$pattern])
                ->orWhereRaw('LOWER(COALESCE(payment_status, \'\')) LIKE ?', [$pattern])
                ->orWhereRaw('LOWER(COALESCE(payment_type, \'\')) LIKE ?', [$pattern])
                ->orWhereRaw('LOWER(COALESCE(service_contract, \'\')) LIKE ?', [$pattern])
                ->orWhereRaw('LOWER(COALESCE(CAST(service_contracts AS CHAR), \'\')) LIKE ?', [$pattern])
                ->orWhereHas('package', fn (Builder $package) => $package->whereRaw('LOWER(COALESCE(name, \'\')) LIKE ?', [$pattern]))
                ->orWhereHas('client', fn (Builder $client) => $client->whereRaw('LOWER(COALESCE(name, \'\')) LIKE ?', [$pattern]));

            if (str_contains('accepted', $term)) {
                $matches->orWhere('status', 'confirmed');
            }
        });
    }
}
