<?php

namespace App\Contracts;

interface ReceiptOcrEngine
{
    public function recognize(string $imagePath): string;
}
