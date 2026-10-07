<?php

namespace App\Services;

use App\Contracts\ReceiptOcrEngine;
use App\Exceptions\ReceiptOcrUnavailable;
use Throwable;

class LocalTesseractReceiptOcr implements ReceiptOcrEngine
{
    public function recognize(string $imagePath): string
    {
        $binary = config('services.receipt_ocr.binary', 'tesseract');
        if (! is_string($binary) || trim($binary) === '') {
            throw new ReceiptOcrUnavailable('Local receipt OCR is not configured.');
        }

        $outputPath = tempnam(sys_get_temp_dir(), '3yos-ocr-out-');
        $errorPath = tempnam(sys_get_temp_dir(), '3yos-ocr-error-');
        if ($outputPath === false || $errorPath === false) {
            if (is_string($outputPath)) {
                @unlink($outputPath);
            }
            if (is_string($errorPath)) {
                @unlink($errorPath);
            }

            throw new ReceiptOcrUnavailable('Temporary OCR files could not be created.');
        }

        $process = null;
        try {
            $process = @proc_open(
                [$binary, $imagePath, 'stdout', '-l', 'eng', '--psm', '6'],
                [
                    0 => ['pipe', 'r'],
                    1 => ['file', $outputPath, 'w'],
                    2 => ['file', $errorPath, 'w'],
                ],
                $pipes,
                null,
                null,
                ['bypass_shell' => true],
            );

            if (! is_resource($process)) {
                throw new ReceiptOcrUnavailable('The local OCR engine could not be started.');
            }

            fclose($pipes[0]);
            $startedAt = microtime(true);
            $exitCode = null;

            do {
                $status = proc_get_status($process);
                if (! $status['running']) {
                    $exitCode = $status['exitcode'];
                    break;
                }

                if (microtime(true) - $startedAt > (float) config('services.receipt_ocr.timeout', 30)) {
                    proc_terminate($process);
                    throw new ReceiptOcrUnavailable('Receipt OCR timed out.');
                }

                usleep(100_000);
            } while (true);

            proc_close($process);
            $process = null;

            $text = file_get_contents($outputPath);
            if ($exitCode !== 0 || ! is_string($text) || trim($text) === '') {
                throw new ReceiptOcrUnavailable('The local OCR engine could not read the receipt.');
            }

            return $text;
        } catch (ReceiptOcrUnavailable $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            throw new ReceiptOcrUnavailable('The local OCR engine could not process this image.', previous: $exception);
        } finally {
            if (is_resource($process)) {
                proc_close($process);
            }
            @unlink($outputPath);
            @unlink($errorPath);
        }
    }
}
