<?php

namespace Tests\Unit;

use App\Services\ReceiptTextParser;
use PHPUnit\Framework\TestCase;

class ReceiptTextParserTest extends TestCase
{
    public function test_it_extracts_gcash_fields_from_the_sample_layout(): void
    {
        $receipt = (new ReceiptTextParser)->parse(
            "Payment\nGenerika Chrysanthemum A\nPaid via GCash\nAmount\n50.00\nTotal\n50.00\nDate\nOct 03, 2026 7:56 PM\nReference No.\n341897185\nPayment successfully processed",
        );

        $this->assertTrue($receipt['detected']);
        $this->assertSame('GCash', $receipt['provider']);
        $this->assertSame('GCash', $receipt['payment_method']);
        $this->assertSame('50.00', $receipt['amount']);
        $this->assertSame('341897185', $receipt['reference_number']);
        $this->assertSame('2026-10-03', $receipt['payment_date']);
    }

    public function test_it_extracts_instapay_transfer_amount_and_optional_reference(): void
    {
        $receipt = (new ReceiptTextParser)->parse(
            "Transfer Successful!\nPHP 3,000.00\nTransfer Amount PHP 3,000.00\nReference Number 859879\nTransfer Method InstaPay",
        );

        $this->assertTrue($receipt['detected']);
        $this->assertSame('InstaPay', $receipt['provider']);
        $this->assertSame('Bank Transfer', $receipt['payment_method']);
        $this->assertSame('3000.00', $receipt['amount']);
        $this->assertSame('859879', $receipt['reference_number']);
        $this->assertNull($receipt['payment_date']);
        $this->assertSame('Needs Review', $receipt['confidence']);
    }

    public function test_it_extracts_bdo_transfer_date_reference_and_not_invoice_number(): void
    {
        $receipt = (new ReceiptTextParser)->parse(
            "Sent!\nPHP 2,000.00\nTotal Amount PHP 2,000.00\nSend Money via InstaPay\nCreated on Sep 29, 2026 03:22 PM\nReference no. BN-20260929-02827771\nInvoice no. 412808\nBDO",
        );

        $this->assertTrue($receipt['detected']);
        $this->assertSame('BDO', $receipt['provider']);
        $this->assertSame('Bank Transfer', $receipt['payment_method']);
        $this->assertSame('2000.00', $receipt['amount']);
        $this->assertSame('BN-20260929-02827771', $receipt['reference_number']);
        $this->assertSame('2026-09-29', $receipt['payment_date']);
    }

    public function test_it_recognizes_maya_and_supports_cash_official_receipts(): void
    {
        $maya = (new ReceiptTextParser)->parse(
            "Maya payment successful\nAmount Paid PHP 125.50\nTransaction ID MAYA12345",
        );
        $cash = (new ReceiptTextParser)->parse(
            "Cash Official Receipt\nReceived from Alex Customer\nThe sum of PHP 500.00\nIn full payment for catering services",
        );

        $this->assertTrue($maya['detected']);
        $this->assertSame('Maya', $maya['payment_method']);
        $this->assertSame('125.50', $maya['amount']);
        $this->assertSame('MAYA12345', $maya['reference_number']);
        $this->assertTrue($cash['detected']);
        $this->assertSame('Cash', $cash['payment_method']);
        $this->assertSame('500.00', $cash['amount']);
        $this->assertNull($cash['reference_number']);
    }

    public function test_it_rejects_the_blank_service_invoice_template_and_unrelated_images(): void
    {
        $parser = new ReceiptTextParser;
        $invoice = $parser->parse(
            "MARIA SANTOS\nSERVICE INVOICE\nNo. 0001\nRECEIVED from __________________\nThe sum of __________________\nIn full / partial payment for __________________",
        );
        $unrelated = $parser->parse('Family event photo, 2026, 5000');

        $this->assertFalse($invoice['detected']);
        $this->assertFalse($unrelated['detected']);
        $this->assertSame('Not Detected', $invoice['confidence']);
    }

    public function test_unknown_provider_receipt_is_flagged_for_manual_method_selection(): void
    {
        $receipt = (new ReceiptTextParser)->parse(
            "Payment Successful\nTransaction Amount PHP 250.00\nPayment confirmation",
        );

        $this->assertTrue($receipt['detected']);
        $this->assertNull($receipt['payment_method']);
        $this->assertSame(
            'Payment receipt detected, but the payment type could not be determined. Please select the payment method manually.',
            $receipt['message'],
        );
        $this->assertSame('Needs Review', $receipt['confidence']);
    }
}
