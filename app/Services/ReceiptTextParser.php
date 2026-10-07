<?php

namespace App\Services;

use Carbon\Carbon;

class ReceiptTextParser
{
    public function parse(string $text): array
    {
        $normalizedText = preg_replace('/[ \t]+/u', ' ', str_replace(["\r\n", "\r"], "\n", $text)) ?? $text;
        $lines = array_values(array_filter(array_map('trim', explode("\n", $normalizedText))));
        $provider = $this->provider($normalizedText);
        $method = $this->paymentMethod($normalizedText);
        $amount = $this->amount($lines, $provider);
        $reference = $this->reference($lines);
        $date = $this->date($lines);
        $hasSuccessfulStatus = preg_match('/\b(successful|success|completed|paid|payment received|sent)\b/i', $normalizedText) === 1;
        $isOfficialReceipt = preg_match('/\bofficial receipt\b/i', $normalizedText) === 1;
        $hasCompletedReceiptDetails = $isOfficialReceipt
            && $amount !== null
            && (
                preg_match('/\bpayment received\b/i', $normalizedText) === 1
                || preg_match('/\breceived from\s*:?\s*[A-Z][A-Z .\'-]{1,}/i', $normalizedText) === 1
                || preg_match('/\b(?:in full|partial payment for)\s*:?\s*[A-Z0-9][A-Z0-9 .\'-]{1,}/i', $normalizedText) === 1
            );
        $isBlankInvoice = preg_match('/\bservice invoice\b/i', $normalizedText) === 1
            && ! $hasSuccessfulStatus
            && ! $hasCompletedReceiptDetails;
        $detected = ! $isBlankInvoice
            && $amount !== null
            && (
                ($hasSuccessfulStatus && preg_match('/\b(payment|transfer|transaction|paid|sent)\b/i', $normalizedText) === 1)
                || $hasCompletedReceiptDetails
            );
        $message = ! $detected
            ? 'The uploaded image does not appear to be a completed payment receipt. Upload payment proof or record the payment manually without attaching this image.'
            : ($method === null
                ? 'Payment receipt detected, but the payment type could not be determined. Please select the payment method manually.'
                : 'Receipt detected. Review every field before recording the payment.');

        return [
            'detected' => $detected,
            'receipt_type' => $this->receiptType($provider, $isOfficialReceipt, $detected),
            'provider' => $provider ?? ($isOfficialReceipt ? 'Official receipt' : null),
            'payment_method' => $method,
            'amount' => $amount,
            'reference_number' => $reference,
            'payment_date' => $date,
            'confidence' => $detected ? 'Needs Review' : 'Not Detected',
            'message' => $message,
        ];
    }

    private function provider(string $text): ?string
    {
        return match (true) {
            preg_match('/\bg\s*cash\b/i', $text) === 1 => 'GCash',
            preg_match('/\bmaya\b/i', $text) === 1 => 'Maya',
            preg_match('/\bbdo\b/i', $text) === 1 => 'BDO',
            preg_match('/\binstapay\b/i', $text) === 1 => 'InstaPay',
            preg_match('/\b(bank transfer|transferred from|transfer successful)\b/i', $text) === 1 => 'Bank transfer',
            preg_match('/\bcash\b/i', $text) === 1 => 'Cash receipt',
            default => null,
        };
    }

    private function paymentMethod(string $text): ?string
    {
        return match (true) {
            preg_match('/\bg\s*cash\b/i', $text) === 1 => 'GCash',
            preg_match('/\bmaya\b/i', $text) === 1 => 'Maya',
            preg_match('/\b(bdo|instapay|bank transfer|transferred from|transfer successful)\b/i', $text) === 1 => 'Bank Transfer',
            preg_match('/\bcash\b/i', $text) === 1 => 'Cash',
            default => null,
        };
    }

    private function receiptType(?string $provider, bool $isOfficialReceipt, bool $detected): string
    {
        return match ($provider) {
            'GCash' => 'GCash payment confirmation',
            'Maya' => 'Maya payment confirmation',
            'BDO' => 'BDO bank transfer',
            'InstaPay', 'Bank transfer' => 'Bank transfer / InstaPay',
            'Cash receipt' => 'Cash receipt',
            default => $isOfficialReceipt ? 'Completed official receipt' : ($detected ? 'Payment receipt' : 'Unclassified'),
        };
    }

    private function amount(array $lines, ?string $provider): ?string
    {
        $labels = $provider === 'GCash'
            ? ['amount paid', 'amount', 'total amount', 'total']
            : ['transfer amount', 'total amount', 'amount paid', 'amount', 'total'];
        if (preg_match('/\bofficial receipt\b/i', implode("\n", $lines)) === 1) {
            array_push($labels, 'sum of', 'sum');
        }

        foreach ($labels as $label) {
            foreach ($lines as $index => $line) {
                if (preg_match('/\b(?:service|transfer)\s+fee\b/i', $line) === 1
                    || preg_match('/\b'.str_replace(' ', '\s+', $label).'\b/i', $line) !== 1) {
                    continue;
                }

                $value = preg_replace('/^.*?\b'.str_replace(' ', '\s+', $label).'\b\s*[:\-]?\s*/i', '', $line, 1);
                $amount = $this->firstAmount($value);
                if ($amount === null && isset($lines[$index + 1])) {
                    $amount = $this->firstAmount($lines[$index + 1]);
                }
                if ($amount !== null) {
                    return $amount;
                }
            }
        }

        return null;
    }

    private function firstAmount(string $text): ?string
    {
        if (preg_match('/(?:PHP|₱)?\s*([0-9][0-9,]*(?:\.\d{1,2})?)/iu', $text, $matches) !== 1) {
            return null;
        }

        $amount = str_replace(',', '', $matches[1]);
        if (! is_numeric($amount) || (float) $amount <= 0) {
            return null;
        }

        return number_format((float) $amount, 2, '.', '');
    }

    private function reference(array $lines): ?string
    {
        $label = '(?:\breference(?:\s*(?:number|no\.?|#))?|\bref(?![a-z])(?:erence)?(?:\s*(?:number|no\.?|#))?|\btransaction\s*(?:id|number|reference))';

        foreach ($lines as $index => $line) {
            if (preg_match('/'.$label.'\s*[:#\-]?\s*([A-Z0-9][A-Z0-9\-]{3,})\b/i', $line, $matches) === 1) {
                return strtoupper($matches[1]);
            }
            if (preg_match('/'.$label.'\s*[:#\-]?\s*$/i', $line) === 1
                && isset($lines[$index + 1])
                && preg_match('/^\s*([A-Z0-9][A-Z0-9\-]{3,})\s*$/i', $lines[$index + 1], $next) === 1) {
                return strtoupper($next[1]);
            }
        }

        return null;
    }

    private function date(array $lines): ?string
    {
        $datePattern = '/\b(?:Jan(?:uary)?|Feb(?:ruary)?|Mar(?:ch)?|Apr(?:il)?|May|Jun(?:e)?|Jul(?:y)?|Aug(?:ust)?|Sep(?:t(?:ember)?)?|Oct(?:ober)?|Nov(?:ember)?|Dec(?:ember)?)\s+\d{1,2},?\s+\d{4}(?:\s+\d{1,2}:\d{2}\s*(?:AM|PM))?|\b\d{4}-\d{2}-\d{2}\b/i';

        foreach ($lines as $index => $line) {
            if (preg_match('/\b(date|created on|transaction date|payment date|processing time)\b/i', $line) !== 1) {
                continue;
            }
            $dateText = $line;
            if (preg_match($datePattern, $dateText, $matches) !== 1) {
                $nextLine = $lines[$index + 1] ?? '';
                if (preg_match($datePattern, $nextLine, $matches) !== 1) {
                    continue;
                }
            }

            try {
                return Carbon::parse($matches[0])->toDateString();
            } catch (\Throwable) {
                return null;
            }
        }

        return null;
    }
}
