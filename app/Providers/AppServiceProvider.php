<?php

namespace App\Providers;

use App\Contracts\ReceiptOcrEngine;
use App\Services\LocalTesseractReceiptOcr;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(ReceiptOcrEngine::class, LocalTesseractReceiptOcr::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
