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

        $enhancedImagePath = null;
        try {
            $startedAt = microtime(true);
            $text = $this->runTesseract($binary, $imagePath, $startedAt);
            $enhancedImagePath = $this->enhancePortraitReceipt($imagePath);
            if ($enhancedImagePath !== null) {
                $text .= "\n".$this->runTesseract($binary, $enhancedImagePath, $startedAt);
            }

            if (trim($text) === '') {
                throw new ReceiptOcrUnavailable('The local OCR engine could not read the receipt.');
            }

            return $text;
        } catch (ReceiptOcrUnavailable $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            throw new ReceiptOcrUnavailable('The local OCR engine could not process this image.', previous: $exception);
        } finally {
            if ($enhancedImagePath !== null) {
                @unlink($enhancedImagePath);
            }
        }
    }

    private function runTesseract(string $binary, string $imagePath, float $startedAt): string
    {
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
            if ($exitCode !== 0 || ! is_string($text)) {
                throw new ReceiptOcrUnavailable('The local OCR engine could not read the receipt.');
            }

            return $text;
        } finally {
            if (is_resource($process)) {
                proc_close($process);
            }
            @unlink($outputPath);
            @unlink($errorPath);
        }
    }

    private function enhancePortraitReceipt(string $imagePath): ?string
    {
        $dimensions = @getimagesize($imagePath);
        if (! is_array($dimensions) || $dimensions[0] < 1 || $dimensions[1] < 1 || $dimensions[1] / $dimensions[0] < 1.5) {
            return null;
        }

        if (! extension_loaded('gd')) {
            throw new ReceiptOcrUnavailable('Enable the PHP GD extension to analyze portrait receipt screenshots.');
        }

        $contents = file_get_contents($imagePath);
        $image = is_string($contents) ? @imagecreatefromstring($contents) : false;
        if ($image === false) {
            return null;
        }

        try {
            $imageWidth = imagesx($image);
            $imageHeight = imagesy($image);
            if ($imageHeight / $imageWidth < 1.5) {
                return null;
            }

            $cropX = (int) round($imageWidth * 0.03);
            $cropY = (int) round($imageHeight * 0.15);
            $cropWidth = (int) round($imageWidth * 0.94);
            $cropHeight = (int) round($imageHeight * 0.63);
            $scale = min(2, 1600 / $cropWidth);
            $enhanced = imagecreatetruecolor((int) round($cropWidth * $scale), (int) round($cropHeight * $scale));
            if ($enhanced === false) {
                return null;
            }

            try {
                imagecopyresampled(
                    $enhanced,
                    $image,
                    0,
                    0,
                    $cropX,
                    $cropY,
                    imagesx($enhanced),
                    imagesy($enhanced),
                    $cropWidth,
                    $cropHeight,
                );

                $path = tempnam(sys_get_temp_dir(), '3yos-ocr-image-');
                if ($path === false) {
                    return null;
                }

                if (! imagepng($enhanced, $path, 6)) {
                    @unlink($path);

                    return null;
                }

                return $path;
            } finally {
                imagedestroy($enhanced);
            }
        } finally {
            imagedestroy($image);
        }
    }
}
