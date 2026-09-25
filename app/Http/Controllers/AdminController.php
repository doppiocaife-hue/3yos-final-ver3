<?php

namespace App\Http\Controllers;

use App\Mail\InquiryReplyMail;
use App\Models\Inquiry;
use App\Models\Package;
use App\Models\Reservation;
use App\Models\Service;
use Illuminate\Database\Eloquent\Builder;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;

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

        $query = Reservation::with('client', 'package')->latest();

        if ($status && in_array($status, ['pending', 'confirmed', 'completed', 'cancelled'], true)) {
            $query->where('status', $status);
        }

        if ($paymentStatus && in_array($paymentStatus, ['Unpaid', 'Downpayment', 'Fully Paid'], true)) {
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
        $reservations = $query->get();
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

    public function exportReservationsCsv(Request $request)
    {
        $query = Reservation::query()->latest();

        $status = $request->input('status');
        $paymentStatus = $request->input('payment_status');
        $search = trim((string) $request->input('search', ''));
        $dateFrom = $request->input('date_from');
        $dateTo = $request->input('date_to');

        if ($status && in_array($status, ['pending', 'confirmed', 'completed', 'cancelled'], true)) {
            $query->where('status', $status);
        }

        if ($paymentStatus && in_array($paymentStatus, ['Unpaid', 'Downpayment', 'Fully Paid'], true)) {
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
        fputcsv($handle, ['Reservation Code', 'Customer Name', 'Email', 'Contact Number', 'Event Type', 'Event Date', 'Venue', 'Status', 'Payment Status', 'Contract Price', 'Amount Paid', 'Balance']);

        foreach ($reservations as $reservation) {
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
                (string) ($reservation->amount_paid ?? 0),
                (string) ($reservation->balance ?? 0),
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
        $data = $request->validate(['reply' => ['required', 'string', 'max:5000']]);

        $mailSent = false;
        $mailError = null;

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

        $inquiry->update(['admin_reply' => $data['reply'], 'replied_at' => now(), 'status' => 'responded']);

        if ($mailSent) {
            return redirect()->route('admin.inquiries.show', $inquiry)->with('success', 'Reply sent to '.$inquiry->email.'.');
        }

        if (in_array(config('mail.default'), ['log', 'array'], true) && $mailError === null) {
            return redirect()->route('admin.inquiries.show', $inquiry)->with(
                'error',
                'Reply saved, but email delivery is disabled because MAIL_MAILER is set to '.config('mail.default').'. Configure SMTP mail settings to send it to '.$inquiry->email.'.'
            );
        }

        return back()->with(
            'error',
            'Reply was saved locally, but email delivery failed. Check MAIL_* settings in .env. Details: '.($mailError?->getMessage() ?? 'Unknown mail error.')
        );
    }

    public function destroyInquiry(Inquiry $inquiry)
    {
        $inquiry->delete();

        return redirect()->route('admin.inquiries')->with('success', 'Inquiry deleted.');
    }

    public function analytics()
    {
        $months = collect(range(0, 11))->map(fn ($offset) => now()->startOfMonth()->subMonths(11 - $offset));
        $reservations = Reservation::select([
            'id',
            'package_id',
            'status',
            'payment_status',
            'amount_paid',
            'created_at',
        ])->get();
        $monthlyReservations = $months->map(fn ($month) => (object) [
            'label' => $month->format('M'),
            'total' => $reservations->filter(fn ($reservation) => $reservation->created_at->format('Y-m') === $month->format('Y-m'))->count(),
        ]);
        $monthlyRevenue = $months->map(fn ($month) => (object) [
            'label' => $month->format('M'),
            'revenue' => $reservations
                ->filter(fn (Reservation $reservation) => $reservation->created_at->format('Y-m') === $month->format('Y-m')
                    && $reservation->status === 'completed'
                    && $reservation->payment_status === 'Fully Paid')
                ->sum('amount_paid'),
        ]);

        $topPackages = Reservation::join('packages', 'packages.id', '=', 'reservations.package_id')
            ->select('packages.name', DB::raw('COUNT(*) as total'))
            ->groupBy('packages.name')
            ->orderByDesc('total')
            ->take(5)
            ->get();

        $activity = Inquiry::where('created_at', '>=', now()->subDays(6)->startOfDay())->get()
            ->groupBy(fn ($inquiry) => $inquiry->created_at->toDateString());
        $activityLabels = collect(range(0, 6))->map(fn ($offset) => now()->subDays(6 - $offset)->format('D'));
        $activityData = collect(range(0, 6))->map(fn ($offset) => $activity->get(now()->subDays(6 - $offset)->toDateString(), collect())->count());

        return view('admin.analytics', compact('monthlyReservations', 'monthlyRevenue', 'topPackages', 'activityLabels', 'activityData'));
    }

    public function updateReservationStatus(Request $request, Reservation $reservation)
    {
        $data = $request->validate([
            'status' => ['sometimes', 'required', 'in:pending,confirmed,completed,cancelled'],
            'payment_status' => ['sometimes', 'nullable', 'in:Unpaid,Downpayment,Fully Paid'],
            'payment_type' => ['sometimes', 'nullable', 'in:Unpaid,Downpayment,Full Payment'],
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

        $amountPaid = (float) ($data['amount_paid'] ?? $reservation->amount_paid ?? 0);
        $totalAmount = (float) ($data['total_cost'] ?? $reservation->total_cost ?? 0);

        if ($hasPaymentUpdate || $hasTotalCostUpdate) {
            if ($hasTotalCostUpdate && $data['total_cost'] === null) {
                return back()->withInput()->withErrors(['total_cost' => 'Enter the contract price before saving payment details.']);
            }

            if ($hasPaymentUpdate) {
                if ($amountPaid <= 0) {
                    $data['payment_status'] = 'Unpaid';
                    $data['payment_type'] = $data['payment_type'] ?? 'Unpaid';
                } elseif ($amountPaid >= $totalAmount) {
                    $data['payment_status'] = 'Fully Paid';
                    $data['payment_type'] = $data['payment_type'] ?? 'Full Payment';
                } else {
                    $data['payment_status'] = 'Downpayment';
                    $data['payment_type'] = $data['payment_type'] ?? 'Downpayment';
                }

                $data['amount_paid'] = $amountPaid;
            }

            $data['balance'] = round(max(0, $totalAmount - ($hasPaymentUpdate ? $amountPaid : ($reservation->amount_paid ?? 0))), 2);
        }

        if (! isset($data['balance']) && $reservation->amount_paid !== null && $reservation->total_cost !== null) {
            $data['balance'] = round(max(0, $totalAmount - ($reservation->amount_paid ?? 0)), 2);
        }

        $reservation->update($data);

        return back()->with('success', 'Reservation saved successfully.');
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

    public function updateInquiryStatus(Request $request, Inquiry $inquiry)
    {
        $inquiry->update($request->validate(['status' => ['required', 'in:new,in_progress,responded,closed']]));

        return back()->with('success', 'Inquiry status updated.');
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
