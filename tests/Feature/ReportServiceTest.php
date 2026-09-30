<?php

namespace Tests\Feature;

use App\Models\Inquiry;
use App\Models\Reservation;
use App\Services\ReportService;
use Carbon\Carbon;
use Illuminate\Support\Str;
use Tests\TestCase;

class ReportServiceTest extends TestCase
{
    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_summaries_use_calendar_period_boundaries_and_live_database_values(): void
    {
        Carbon::setTestNow('2026-09-29 15:30:00');
        config(['app.timezone' => 'Asia/Manila']);

        $this->createReservation('2026-09-29 23:59:59', 'confirmed', 20134);
        $this->createReservation('2026-09-28 00:00:00', 'completed', 9000);
        $this->createReservation('2026-09-01 00:00:00', 'cancelled', 1000);
        $this->createReservation('2026-01-01 00:00:00', 'pending', 4000);
        $this->createReservation('2026-08-31 23:59:59', 'confirmed', 5000);

        foreach (['2026-09-29 10:00:00', '2026-09-28 10:00:00', '2026-09-01 10:00:00', '2026-01-01 10:00:00', '2026-08-31 10:00:00'] as $createdAt) {
            Inquiry::unguarded(fn () => Inquiry::create([
                'full_name' => 'Report Client',
                'contact_number' => '09171234567',
                'email' => 'report@example.com',
                'subject' => 'Report inquiry',
                'category' => 'Catering',
                'message' => 'Please send a report.',
                'status' => 'new',
                'created_at' => $createdAt,
                'updated_at' => $createdAt,
            ]));
        }

        $service = app(ReportService::class);

        $this->assertSame(1, $service->getSummary('daily')['reservation_count']);
        $this->assertSame(2, $service->getSummary('weekly')['reservation_count']);
        $this->assertSame(3, $service->getSummary('monthly')['reservation_count']);
        $this->assertSame(5, $service->getSummary('yearly')['reservation_count']);
        $this->assertSame(20134.0, $service->getSummary('daily')['estimated_revenue']);
        $this->assertSame(2, $service->getSummary('weekly')['inquiry_count']);
    }

    public function test_report_page_and_csv_and_excel_downloads_use_the_live_period_summaries(): void
    {
        Carbon::setTestNow('2026-09-29 15:30:00');
        config(['app.timezone' => 'Asia/Manila']);
        $this->createReservation('2026-09-29 09:00:00', 'confirmed', 20134);
        $this->createReservation('2026-09-28 12:00:00', 'completed', 5000);
        $this->createReservation('2026-09-01 08:00:00', 'cancelled', 1000);
        $this->createReservation('2026-01-01 08:00:00', 'pending', 4000);
        $this->createReservation('2026-08-31 23:59:59', 'confirmed', 7000);

        $session = ['is_admin' => true, 'admin_role' => 'full'];
        $page = $this->withSession($session)->get(route('admin.reports'));
        $page->assertOk();
        $page->assertSee('Today · Sep 29, 2026');
        $page->assertSee('Download Excel');
        $page->assertSee('Download CSV');
        $page->assertSee('₱20,134.00');
        $page->assertSee('This Week · Sep 28 – Oct 4, 2026');

        $periods = [
            'daily' => ['count' => 1, 'revenue' => 20134, 'filename' => '2026-09-29'],
            'weekly' => ['count' => 2, 'revenue' => 25134, 'filename' => '2026-09-29'],
            'monthly' => ['count' => 3, 'revenue' => 25134, 'filename' => '2026-09'],
            'yearly' => ['count' => 5, 'revenue' => 32134, 'filename' => '2026'],
        ];

        foreach ($periods as $period => $expected) {
            $csv = $this->withSession($session)->get(route('admin.reports.export', ['period' => $period]));
            $csv->assertOk();
            $csv->assertHeader('Content-Type', 'text/csv; charset=UTF-8');
            $csv->assertHeader('Content-Disposition', 'attachment; filename="3YOS-Catering-'.ucfirst($period).'-Report-'.$expected['filename'].'.csv"');
            $csv->assertSee('Reservations,'.$expected['count']);

            $excel = $this->withSession($session)->get(route('admin.reports.export.excel', ['period' => $period]));
            $excel->assertDownload('3YOS-Catering-'.ucfirst($period).'-Report-'.$expected['filename'].'.xlsx');

            $archive = new \ZipArchive();
            $this->assertSame(true, $archive->open($excel->baseResponse->getFile()->getPathname()));
            $sheet = $archive->getFromName('xl/worksheets/sheet1.xml');
            $styles = $archive->getFromName('xl/styles.xml');
            $workbook = $archive->getFromName('xl/workbook.xml');
            $this->assertNotFalse($sheet);
            $this->assertNotFalse($styles);
            $this->assertNotFalse($workbook);
            $this->assertStringContainsString('Estimated Revenue', $sheet);
            $this->assertStringContainsString('3YOS CATERING', $sheet);
            $this->assertStringContainsString((string) $expected['revenue'], $sheet);
            $this->assertStringContainsString('pane', $sheet);
            $this->assertStringContainsString('numFmt', $styles);
            $this->assertStringContainsString(ucfirst($period).' Report', $workbook);
            $archive->close();
        }
    }

    public function test_reports_separate_booking_financials_from_payment_and_refund_transactions(): void
    {
        Carbon::setTestNow('2026-09-29 15:30:00');
        config(['app.timezone' => 'Asia/Manila']);

        $reservation = $this->createReservation('2026-09-29 09:00:00', 'confirmed', 1000);
        $reservation->update(['total_cost' => 1000]);
        $session = ['is_admin' => true, 'admin_role' => 'full', 'admin_name' => 'Report Tester'];

        $this->withSession($session)->post(route('admin.reservations.payments.store', $reservation), [
            'payment_date' => '2026-09-29',
            'payment_type' => 'Downpayment',
            'amount' => 800,
            'payment_method' => 'Cash',
        ])->assertRedirect();

        $this->withSession($session)->post(route('admin.reservations.refunds.store', $reservation), [
            'request_key' => (string) Str::uuid(),
            'refund_date' => '2026-09-29',
            'refund_amount' => 300,
            'refund_method' => 'Cash',
        ])->assertRedirect();

        $this->assertSame('2026-09-29', $reservation->payments()->firstOrFail()->payment_date->toDateString());
        $this->assertSame('2026-09-29', $reservation->refunds()->firstOrFail()->refund_date->toDateString());
        $summary = app(ReportService::class)->getSummary('daily');
        $this->assertSame('2026-09-29', $summary['period_start']->toDateString());
        $this->assertEquals(1000.0, $summary['contract_value']);
        $this->assertEquals(800.0, $summary['gross_paid']);
        $this->assertEquals(300.0, $summary['total_refunded']);
        $this->assertEquals(500.0, $summary['net_paid']);
        $this->assertEquals(500.0, $summary['outstanding_balance']);
        $this->assertEquals(800.0, $summary['gross_payments_in_period']);
        $this->assertEquals(300.0, $summary['refunds_in_period']);
        $this->assertEquals(500.0, $summary['net_collected_in_period']);

        $page = $this->withSession($session)->get(route('admin.reports'));
        $page->assertOk()
            ->assertSee('Payments, refunds & balances')
            ->assertSee('Gross paid')
            ->assertSee('−₱300.00')
            ->assertSee('₱500.00');

        $csv = $this->withSession($session)->get(route('admin.reports.export', ['period' => 'daily']));
        $csv->assertOk()
            ->assertSee('"Gross Paid (Bookings Created in Period)",800', false)
            ->assertSee('"Refunded (Bookings Created in Period)",300', false)
            ->assertSee('"Net Collected (Transactions in Period)",500', false);

        $excel = $this->withSession($session)->get(route('admin.reports.export.excel', ['period' => 'daily']));
        $excel->assertDownload();
        $archive = new \ZipArchive();
        $this->assertSame(true, $archive->open($excel->baseResponse->getFile()->getPathname()));
        $sheet = $archive->getFromName('xl/worksheets/sheet1.xml');
        $this->assertNotFalse($sheet);
        $this->assertStringContainsString('Gross Paid (Bookings Created in Period)', $sheet);
        $this->assertStringContainsString('Refunds (Transactions in Period)', $sheet);
        $this->assertStringContainsString('500', $sheet);
        $archive->close();
    }

    private function createReservation(string $createdAt, string $status, int $budget): Reservation
    {
        return Reservation::unguarded(fn () => Reservation::create([
            'full_name' => 'Report Client',
            'contact_number' => '09171234567',
            'email' => 'report@example.com',
            'address' => 'Report Street',
            'event_type' => 'Wedding',
            'event_date' => '2026-10-01',
            'event_time' => '18:00',
            'venue' => 'Report Hall',
            'guest_count' => 50,
            'estimated_budget' => $budget,
            'status' => $status,
            'created_at' => $createdAt,
            'updated_at' => $createdAt,
        ]));
    }
}