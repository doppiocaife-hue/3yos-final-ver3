<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'ensure.admin' => \App\Http\Middleware\EnsureAdmin::class,
            'ensure.full-admin' => \App\Http\Middleware\EnsureFullAdmin::class,
            'capture.activity' => \App\Http\Middleware\CaptureActivity::class,
            'confirm.admin-password' => \App\Http\Middleware\ConfirmAdminPassword::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
