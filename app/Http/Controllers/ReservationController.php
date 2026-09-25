<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreReservationRequest;
use App\Mail\ReservationConfirmationMail;
use App\Models\Client;
use App\Models\Package;
use App\Models\Reservation;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Mail;
use ReCaptcha\ReCaptcha;

class ReservationController extends Controller
{
    public function availability(\Illuminate\Http\Request $request)
    {
        $data = $request->validate([
            'date' => ['required', 'date', 'after_or_equal:' . now()->addDays(2)->toDateString(), function ($attribute, $value, $fail) {
                $minDate = now()->addDays(2)->toDateString();
                if ($value < $minDate) {
                    $fail('Reservations must be scheduled at least 2 days in advance.');
                }
            }],
        ]);
        $bookings = Reservation::whereDate('event_date', $data['date'])
            ->where('status', '!=', 'cancelled')
            ->count();

        return response()->json(['bookings' => $bookings, 'remaining' => max(0, 3 - $bookings), 'available' => $bookings < 3]);
    }

    public function store(StoreReservationRequest $request)
    {
        if (now()->timestamp - (int) $request->input('form_started') < 3) {
            return back()->withInput()->withErrors(['full_name' => 'Unable to submit this request. Please try again.']);
        }

        // Verify reCAPTCHA
        $recaptcha = new ReCaptcha(config('services.recaptcha.secret_key'));
        $resp = $recaptcha->verify($request->input('g-recaptcha-response'), $_SERVER['REMOTE_ADDR'] ?? '');
        
        if (!$resp->isSuccess()) {
            return back()->withInput()->withErrors(['g-recaptcha-response' => 'Please verify that you are not a robot.']);
        }

        $bookings = Reservation::whereDate('event_date', $request->input('event_date'))
            ->where('status', '!=', 'cancelled')
            ->count();

        if ($bookings >= 3) {
            return back()->withInput()->withErrors(['event_date' => 'This date is fully booked. Please select another date.']);
        }

        $client = Client::firstOrCreate(
            ['email' => $request->input('email')],
            [
                'name' => $request->input('full_name'),
                'phone' => $request->input('contact_number'),
                'address' => $request->input('address'),
            ]
        );

        $reservationCode = $this->generateReservationCode();
        $package = Package::findOrFail($request->input('package_id'));

        $reservation = Reservation::create([
            'client_id' => $client->id,
            'package_id' => $request->input('package_id'),
            'full_name' => $request->input('full_name'),
            'contact_number' => $request->input('contact_number'),
            'email' => $request->input('email'),
            'address' => $request->input('address'),
            'event_type' => $request->input('event_type'),
            'event_date' => $request->input('event_date'),
            'event_time' => $request->input('event_time'),
            'venue' => $request->input('venue'),
            'guest_count' => $request->input('guest_count'),
            'estimated_budget' => $package->estimatedTotalFor((int) $request->input('guest_count')),
            'additional_services' => $request->input('additional_services'),
            'special_requests' => $request->input('special_requests'),
            'additional_notes' => $request->input('additional_notes'),
            'status' => 'pending',
            'reservation_code' => $reservationCode,
        ]);

        $mailSent = false;
        $mailError = null;

        try {
            Mail::to($reservation->email, $reservation->full_name)->send(new ReservationConfirmationMail($reservation));
            $mailSent = ! in_array(config('mail.default'), ['log', 'array'], true);
        } catch (\Throwable $exception) {
            $mailError = $exception;
            report($exception);
        }

        $request->session()->flash('reservation_code', $reservationCode);
        $request->session()->flash('reservation_status', 'pending');

        $message = 'Your reservation request has been received. Your reservation ID is '.$reservationCode.'. Please keep this code to check your reservation status.';

        if ($mailSent) {
            $message .= ' A confirmation email was sent to '.$reservation->email.'.';
        } elseif (in_array(config('mail.default'), ['log', 'array'], true)) {
            $message .= ' Email delivery is not enabled yet; configure Gmail SMTP to receive this ID by email.';
        } else {
            $message .= ' We could not send the confirmation email; please keep this ID and contact us if needed.';
        }

        return redirect()->back()->with('success', $message);
    }

    private function generateReservationCode(): string
    {
        do {
            $code = 'RES-' . strtoupper(Str::random(8));
        } while (Reservation::where('reservation_code', $code)->exists());

        return $code;
    }
}
