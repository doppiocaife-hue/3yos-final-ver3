<?php

namespace Tests\Feature;

use App\Models\Package;
use App\Models\Reservation;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AdminReservationDetailTest extends TestCase
{
    private const ADMIN = ['is_admin' => true, 'admin_role' => 'full', 'admin_name' => 'Detail Tester', 'admin_email' => 'detail@3yos.com'];

    private function reservation(array $overrides = []): Reservation
    {
        $package = Package::create(['name' => 'Detail Package', 'slug' => 'detail-'.uniqid(), 'price' => 800, 'min_guests' => 20, 'max_guests' => 200]);

        return Reservation::create($overrides + [
            'package_id' => $package->id,
            'full_name' => 'Detail Client',
            'contact_number' => '09171234567',
            'email' => 'detail-'.uniqid().'@example.com',
            'address' => '1 Detail Street',
            'event_type' => 'Wedding',
            'event_date' => now()->addMonth()->toDateString(),
            'event_time' => '18:00',
            'venue' => 'Detail Hall',
            'guest_count' => 60,
            'estimated_budget' => 48000,
            'total_cost' => 48000,
            'status' => 'pending',
            'reservation_code' => 'RES-DET'.random_int(100000, 999999),
        ]);
    }

    private function pngUpload(string $name): UploadedFile
    {
        return UploadedFile::fake()->createWithContent(
            $name,
            base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII='),
        );
    }

    public function test_guest_cannot_view_the_reservation_detail_page(): void
    {
        $reservation = $this->reservation();

        $this->get(route('admin.reservations.show', $reservation))
            ->assertRedirect(route('admin.login'));
    }

    public function test_admin_can_view_the_reservation_detail_page(): void
    {
        $reservation = $this->reservation(['status' => 'confirmed']);

        $response = $this->withSession(self::ADMIN)->get(route('admin.reservations.show', $reservation));

        $response->assertOk();
        $response->assertSee($reservation->full_name);
        $response->assertSee($reservation->reservation_code);
        $response->assertSee('Customer');
        $response->assertSee('Event');
        $response->assertSee('Package');
        $response->assertSee('Contract');
        $response->assertSee('Payment');
        $response->assertSee('Notes');
        $response->assertSee('Activity');
        $response->assertSee('Submitted');
        $response->assertSee('Under Review');
        $response->assertSee('Accepted');
        $response->assertSee('Completed');
    }

    public function test_admin_can_preview_download_and_delete_multiple_contract_images(): void
    {
        Storage::fake('public');
        $reservation = $this->reservation();

        $this->withSession(self::ADMIN)
            ->post(route('admin.reservations.contract', $reservation), [
                'service_contract' => [
                    $this->pngUpload('contract-one.png'),
                    $this->pngUpload('contract-two.png'),
                ],
            ])
            ->assertRedirect();

        $files = $reservation->fresh()->contractFiles();
        $this->assertCount(2, $files);
        $this->assertTrue(Storage::disk('public')->exists($files[0]));
        $this->assertTrue(Storage::disk('public')->exists($files[1]));

        $detail = $this->withSession(self::ADMIN)->get(route('admin.reservations.show', $reservation));
        $detail->assertOk();
        $detail->assertSee('Contract 1');
        $detail->assertSee('Contract 2');
        $detail->assertSee(basename($files[0]));
        $detail->assertSee(basename($files[1]));
        $detail->assertSee('id="contract-preview-dialog"', false);
        $detail->assertSee('data-contract-preview', false);
        $detail->assertSee('data-contract-preview-close', false);
        $detail->assertSee('Download');
        $detail->assertDontSee('storage/service-contracts/');

        $preview = $this->withSession(self::ADMIN)
            ->get(route('admin.reservations.contract.preview', [$reservation, 0]));
        $preview->assertOk();
        $preview->assertHeader('Content-Type', 'image/png');
        $preview->assertHeader('X-Content-Type-Options', 'nosniff');
        $cacheDirectives = explode(', ', $preview->headers->get('Cache-Control'));
        sort($cacheDirectives);
        $this->assertSame(['max-age=0', 'no-store', 'private'], $cacheDirectives);
        $preview->assertHeader('Content-Disposition', 'inline; filename='.basename($files[0]));

        $download = $this->withSession(self::ADMIN)
            ->get(route('admin.reservations.contract.download', [$reservation, 1]));
        $download->assertOk();
        $download->assertHeader('Content-Disposition', 'attachment; filename='.basename($files[1]));

        $this->withSession(self::ADMIN)
            ->delete(route('admin.reservations.contract.delete', [$reservation, 1]))
            ->assertRedirect();

        $this->withSession(self::ADMIN)
            ->get(route('admin.reservations.contract.preview', [$reservation, 1]))
            ->assertNotFound();
        $this->withSession(self::ADMIN)
            ->get(route('admin.reservations.show', $reservation))
            ->assertOk()
            ->assertSee('Contract 1')
            ->assertDontSee('Contract 2');
    }

    public function test_contract_preview_and_download_require_admin_access(): void
    {
        Storage::fake('public');
        $reservation = $this->reservation();
        $contractPath = $this->pngUpload('private-contract.png')->store('service-contracts', 'public');
        $reservation->update(['service_contracts' => [$contractPath]]);

        $this->get(route('admin.reservations.contract.preview', [$reservation, 0]))
            ->assertRedirect(route('admin.login'));
        $this->get(route('admin.reservations.contract.download', [$reservation, 0]))
            ->assertRedirect(route('admin.login'));
    }

    public function test_missing_contract_files_are_reported_and_not_previewed(): void
    {
        Storage::fake('public');
        $reservation = $this->reservation([
            'service_contracts' => ['service-contracts/missing-contract.png'],
        ]);

        $this->withSession(self::ADMIN)
            ->get(route('admin.reservations.show', $reservation))
            ->assertOk()
            ->assertSee('Contract 1')
            ->assertSee('File is no longer available.')
            ->assertDontSee(route('admin.reservations.contract.download', [$reservation, 0]), false);

        $this->withSession(self::ADMIN)
            ->get(route('admin.reservations.contract.preview', [$reservation, 0]))
            ->assertNotFound();
        $this->withSession(self::ADMIN)
            ->get(route('admin.reservations.contract.download', [$reservation, 0]))
            ->assertNotFound();
    }

    public function test_reservations_list_links_to_the_detail_page(): void
    {
        $reservation = $this->reservation();

        $response = $this->withSession(self::ADMIN)->get(route('admin.reservations'));

        $response->assertOk();
        $response->assertSee(route('admin.reservations.show', $reservation), false);
    }

    public function test_admin_can_save_an_internal_note_from_the_detail_page(): void
    {
        Mail::fake();
        $reservation = $this->reservation();

        $this->withSession(self::ADMIN)
            ->patch(route('admin.reservations.status', $reservation), ['admin_notes' => 'Client requested vegan menu.'])
            ->assertSessionHasNoErrors();

        $this->assertSame('Client requested vegan menu.', $reservation->fresh()->admin_notes);
        $this->assertSame('pending', $reservation->fresh()->status);
    }

    public function test_admin_created_booking_is_blocked_once_four_accepted_bookings_exist_for_the_date(): void
    {
        Mail::fake();
        $date = now()->addMonths(2)->toDateString();
        $this->reservation(['status' => 'confirmed', 'event_date' => $date]);
        $this->reservation(['status' => 'confirmed', 'event_date' => $date]);
        $this->reservation(['status' => 'confirmed', 'event_date' => $date]);
        $this->reservation(['status' => 'confirmed', 'event_date' => $date]);

        $package = Package::create(['name' => 'Overflow Package', 'slug' => 'overflow-'.uniqid(), 'price' => 500]);

        $response = $this->withSession(self::ADMIN)->post(route('admin.reservations.store'), [
            'full_name' => 'Overflow Client',
            'contact_number' => '09171234567',
            'email' => 'overflow@example.com',
            'address' => '1 Overflow Street',
            'event_type' => 'Birthday',
            'event_date' => $date,
            'event_time' => '18:00',
            'venue' => 'Overflow Hall',
            'guest_count' => 30,
            'package_id' => $package->id,
        ]);

        $response->assertSessionHasErrors('event_date');
        $this->assertDatabaseMissing('reservations', ['email' => 'overflow@example.com']);
    }
}
