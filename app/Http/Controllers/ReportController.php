<?php

namespace App\Http\Controllers;

use App\Services\ReportService;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\Log;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Common\Entity\Style\Border;
use OpenSpout\Common\Entity\Style\BorderPart;
use OpenSpout\Common\Entity\Style\Color;
use OpenSpout\Common\Entity\Style\Style;
use OpenSpout\Writer\XLSX\Entity\SheetView;
use OpenSpout\Writer\XLSX\Options;
use OpenSpout\Writer\XLSX\Options\HeaderFooter;
use OpenSpout\Writer\XLSX\Options\PageOrientation;
use OpenSpout\Writer\XLSX\Options\PageSetup;
use OpenSpout\Writer\XLSX\Options\PaperSize;
use OpenSpout\Writer\XLSX\Writer;
use Throwable;

class ReportController extends Controller
{
    public function index(ReportService $reportService)
    {
        $daily = $reportService->getSummary('daily');
        $weekly = $reportService->getSummary('weekly');
        $monthly = $reportService->getSummary('monthly');
        $yearly = $reportService->getSummary('yearly');

        return view('admin.reports', compact('daily', 'weekly', 'monthly', 'yearly'));
    }

    public function export(ReportService $reportService = null, string $period = 'daily')
    {
        $reportService ??= app(ReportService::class);
        $period = strtolower($period);

        if (! in_array($period, ['daily', 'weekly', 'monthly', 'yearly'], true)) {
            abort(404);
        }

        try {
            $summary = $reportService->getSummary($period);
            $filename = $this->filename($summary, $period, 'csv');
            $csv = $this->buildCsv($summary, $period);
            if ($csv === '') {
                throw new \RuntimeException('The CSV report was empty.');
            }
        } catch (Throwable $exception) {
            Log::error('Unable to generate report CSV.', ['period' => $period, 'exception' => $exception]);

            return response()->json(['message' => 'Unable to generate the report. Please try again.'], 500);
        }

        return response($csv, 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ]);
    }

    public function exportExcel(ReportService $reportService, string $period)
    {
        $period = strtolower($period);

        if (! in_array($period, ['daily', 'weekly', 'monthly', 'yearly'], true)) {
            abort(404);
        }

        $path = tempnam(sys_get_temp_dir(), '3yos-report-');
        if ($path === false) {
            return response()->json(['message' => 'Unable to generate the report. Please try again.'], 500);
        }

        try {
            $summary = $reportService->getSummary($period);
            $this->writeExcel($path, $summary, $period);

            return response()->download(
                $path,
                $this->filename($summary, $period, 'xlsx'),
                ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'],
            )->deleteFileAfterSend(true);
        } catch (Throwable $exception) {
            @unlink($path);
            Log::error('Unable to generate report workbook.', ['period' => $period, 'exception' => $exception]);

            return response()->json(['message' => 'Unable to generate the report. Please try again.'], 500);
        }
    }

    private function filename(array $summary, string $period, string $extension): string
    {
        /** @var CarbonInterface $periodStart */
        $periodStart = $summary['period_start'];
        /** @var CarbonInterface $generatedAt */
        $generatedAt = $summary['generated_at'];
        $dateSuffix = match ($period) {
            'daily', 'weekly' => $generatedAt->format('Y-m-d'),
            'monthly' => $periodStart->format('Y-m'),
            'yearly' => $periodStart->format('Y'),
        };

        return '3YOS-Catering-'.ucfirst($period).'-Report-'.$dateSuffix.'.'.$extension;
    }

    private function writeExcel(string $path, array $summary, string $period): void
    {
        $options = new Options();
        $options->mergeCells(0, 1, 1, 1);
        $options->mergeCells(0, 2, 1, 2);
        $options->mergeCells(0, 3, 1, 3);
        $options->mergeCells(0, 4, 1, 4);
        $options->setPageSetup(new PageSetup(PageOrientation::PORTRAIT, PaperSize::A4, 1, 1));
        $options->setHeaderFooter(new HeaderFooter(oddFooter: '&C3YOS Catering | Confidential'));

        $writer = new Writer($options);
        $writer->openToFile($path);
        $sheet = $writer->getCurrentSheet();
        $sheet->setName(ucfirst($period).' Report');
        $sheet->setColumnWidth(36, 1);
        $sheet->setColumnWidth(22, 2);
        $sheet->setSheetView((new SheetView())->setShowGridLines(false)->setFreezeRow(7));

        $titleStyle = (new Style())->setFontBold()->setFontSize(18)->setFontColor(Color::WHITE)->setBackgroundColor('176B68');
        $subtitleStyle = (new Style())->setFontBold()->setFontSize(13)->setFontColor(Color::WHITE)->setBackgroundColor('176B68');
        $metaStyle = (new Style())->setFontSize(10)->setFontColor('52616B');
        $tableBorder = new Border(new BorderPart(Border::BOTTOM, 'D7E2E4', Border::WIDTH_THIN));
        $headerStyle = (new Style())->setFontBold()->setFontColor(Color::WHITE)->setBackgroundColor('244A57')->setBorder($tableBorder);
        $labelStyle = (new Style())->setFontColor('24353D')->setBorder($tableBorder);
        $valueStyle = (new Style())->setFontBold()->setFontColor('24353D')->setCellAlignment('right')->setFormat('#,##0')->setBorder($tableBorder);
        $financialValueStyle = (new Style())->setFontBold()->setFontColor('24353D')->setCellAlignment('right')->setFormat('"₱"#,##0.00')->setBorder($tableBorder);
        $revenueLabelStyle = (new Style())->setFontBold()->setFontSize(12)->setFontColor('176B68')->setBackgroundColor('E7F4F1')->setBorder($tableBorder);
        $revenueValueStyle = (new Style())->setFontBold()->setFontSize(13)->setFontColor('176B68')->setBackgroundColor('E7F4F1')->setCellAlignment('right')->setFormat('"₱"#,##0.00')->setBorder($tableBorder);
        $periodStart = $summary['period_start'];
        $periodEnd = $summary['period_end'];
        $periodText = $periodStart->format('F j, Y');
        if (! $periodStart->isSameDay($periodEnd)) {
            $periodText = $periodStart->format('F j, Y').' – '.$periodEnd->format('F j, Y');
        }

        $writer->addRow(Row::fromValues(['3YOS CATERING'], $titleStyle)->setHeight(32));
        $writer->addRow(Row::fromValues([strtoupper(ucfirst($period).' Operations Report')], $subtitleStyle)->setHeight(24));
        $writer->addRow(Row::fromValues(['Period: '.$periodText], $metaStyle)->setHeight(21));
        $writer->addRow(Row::fromValues(['Generated: '.$summary['generated_at']->format('F j, Y g:i A T')], $metaStyle)->setHeight(21));
        $writer->addRow(Row::fromValues([], null)->setHeight(10));
        $writer->addRow(Row::fromValues(['METRIC', 'VALUE'], $headerStyle)->setHeight(23));

        $metrics = [
            ['Reservations', $summary['reservation_count']],
            ['Confirmed', $summary['confirmed_reservations']],
            ['Completed', $summary['completed_events']],
            ['Cancelled', $summary['cancelled_reservations']],
            ['Inquiries', $summary['inquiry_count']],
            ['Contract Value (Bookings Created in Period)', $summary['contract_value']],
            ['Gross Paid (Bookings Created in Period)', $summary['gross_paid']],
            ['Refunded (Bookings Created in Period)', $summary['total_refunded']],
            ['Net Paid (Bookings Created in Period)', $summary['net_paid']],
            ['Outstanding Balance (Current Bookings)', $summary['outstanding_balance']],
            ['Gross Payments (Transactions in Period)', $summary['gross_payments_in_period']],
            ['Refunds (Transactions in Period)', $summary['refunds_in_period']],
            ['Net Collected (Transactions in Period)', $summary['net_collected_in_period']],
        ];
        $financialLabels = [
            'Contract Value (Bookings Created in Period)',
            'Gross Paid (Bookings Created in Period)',
            'Refunded (Bookings Created in Period)',
            'Net Paid (Bookings Created in Period)',
            'Outstanding Balance (Current Bookings)',
            'Gross Payments (Transactions in Period)',
            'Refunds (Transactions in Period)',
            'Net Collected (Transactions in Period)',
        ];

        foreach ($metrics as [$label, $value]) {
            $metricValueStyle = in_array($label, $financialLabels, true) ? $financialValueStyle : $valueStyle;
            $writer->addRow(Row::fromValuesWithStyles([$label, $value], null, [$labelStyle, $metricValueStyle])->setHeight(21));
        }

        $writer->addRow(Row::fromValuesWithStyles(['Estimated Revenue', $summary['estimated_revenue']], null, [$revenueLabelStyle, $revenueValueStyle])->setHeight(28));
        $writer->close();
    }

    protected function buildCsv(array $summary, string $period): string
    {
        $rows = [
            ['Period', ucfirst($period)],
            ['Reservations', $summary['reservation_count']],
            ['Confirmed Reservations', $summary['confirmed_reservations']],
            ['Completed Events', $summary['completed_events']],
            ['Cancelled Reservations', $summary['cancelled_reservations']],
            ['Inquiries', $summary['inquiry_count']],
            ['Estimated Revenue', $summary['estimated_revenue']],
            ['Contract Value (Bookings Created in Period)', $summary['contract_value']],
            ['Gross Paid (Bookings Created in Period)', $summary['gross_paid']],
            ['Refunded (Bookings Created in Period)', $summary['total_refunded']],
            ['Net Paid (Bookings Created in Period)', $summary['net_paid']],
            ['Outstanding Balance (Current Bookings)', $summary['outstanding_balance']],
            ['Gross Payments (Transactions in Period)', $summary['gross_payments_in_period']],
            ['Refunds (Transactions in Period)', $summary['refunds_in_period']],
            ['Net Collected (Transactions in Period)', $summary['net_collected_in_period']],
        ];

        $handle = fopen('php://temp', 'r+');

        foreach ($rows as $row) {
            fputcsv($handle, $row);
        }

        rewind($handle);
        $csv = stream_get_contents($handle);
        fclose($handle);

        return $csv !== false ? $csv : '';
    }
}
