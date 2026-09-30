<?php

use App\Http\Controllers\ActivityLogController;
use App\Http\Controllers\AdminController;
use App\Http\Controllers\AdminGalleryController;
use App\Http\Controllers\AdminPackageController;
use App\Http\Controllers\AdminReservationController;
use App\Http\Controllers\AdminServiceController;
use App\Http\Controllers\AdminUserController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\BackupController;
use App\Http\Controllers\InquiryController;
use App\Http\Controllers\PublicController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\ReservationController;
use App\Http\Controllers\ReservationPaymentController;
use Illuminate\Support\Facades\Route;

Route::get('/', [PublicController::class, 'home'])->name('home');
Route::get('/about', [PublicController::class, 'about'])->name('about');
Route::get('/services', [PublicController::class, 'services'])->name('services');
Route::get('/packages', [PublicController::class, 'packages'])->name('packages');
Route::get('/packages/{package:slug}', [PublicController::class, 'packageShow'])->name('packages.show');
Route::get('/gallery', [PublicController::class, 'gallery'])->name('gallery');
Route::get('/gallery-images/{path}', [PublicController::class, 'galleryImage'])->where('path', '.*')->name('gallery.image');
Route::get('/package-images/{path}', [PublicController::class, 'packageImage'])->where('path', '.*')->name('package.image');
Route::get('/reservation', [PublicController::class, 'reservation'])->name('reservation');
Route::get('/reservation/status', [PublicController::class, 'reservationStatus'])->name('reservation.status');
Route::get('/reservation/availability', [ReservationController::class, 'availability'])->name('reservation.availability');
Route::post('/reservation', [ReservationController::class, 'store'])->middleware('throttle:5,10')->name('reservation.store');
Route::get('/inquiry', [PublicController::class, 'inquiry'])->name('inquiry');
Route::post('/inquiry', [InquiryController::class, 'store'])->middleware('throttle:5,10')->name('inquiry.store');
Route::get('/contact', [PublicController::class, 'contact'])->name('contact');

Route::get('/admin/login', [AuthController::class, 'showLoginForm'])->name('admin.login');
Route::post('/admin/login', [AuthController::class, 'login'])->name('admin.login.post');
Route::get('/admin/forgot-password', [AuthController::class, 'showForgotPasswordForm'])->middleware('guest')->name('password.request');
Route::post('/admin/forgot-password', [AuthController::class, 'sendPasswordResetLink'])->middleware(['guest', 'throttle:3,10'])->name('password.email');
Route::get('/admin/reset-password/{token}', [AuthController::class, 'showResetPasswordForm'])->middleware('guest')->name('password.reset');
Route::post('/admin/reset-password', [AuthController::class, 'resetPassword'])->middleware(['guest', 'throttle:5,10'])->name('password.update');
Route::post('/admin/logout', [AuthController::class, 'logout'])->name('admin.logout');

Route::middleware(['ensure.admin', 'capture.activity'])->prefix('admin')->group(function () {
    Route::get('/', [AdminController::class, 'index'])->name('admin.dashboard');
    Route::get('/reservations', [AdminController::class, 'reservations'])->name('admin.reservations');
    Route::get('/reservations/create', [AdminReservationController::class, 'create'])->name('admin.reservations.create');
    Route::post('/reservations', [AdminReservationController::class, 'store'])->name('admin.reservations.store');
    Route::get('/reservations/export', [AdminController::class, 'exportReservationsCsv'])->name('admin.reservations.export');
    Route::patch('/reservations/{reservation}/status', [AdminController::class, 'updateReservationStatus'])->name('admin.reservations.status');
    Route::post('/reservations/{reservation}/service-contract', [AdminController::class, 'uploadReservationContract'])->name('admin.reservations.contract');
    Route::delete('/reservations/{reservation}/service-contract/{contract}', [AdminController::class, 'deleteReservationContract'])->name('admin.reservations.contract.delete');
    Route::scopeBindings()->group(function () {
        Route::get('/reservations/{reservation}/payments', [ReservationPaymentController::class, 'index'])->name('admin.reservations.payments');
        Route::get('/reservations/{reservation}/payments/print', [ReservationPaymentController::class, 'print'])->name('admin.reservations.payments.print');
        Route::post('/reservations/{reservation}/payments', [ReservationPaymentController::class, 'store'])->name('admin.reservations.payments.store');
        Route::post('/reservations/{reservation}/refunds', [ReservationPaymentController::class, 'storeRefund'])
            ->withoutMiddleware('capture.activity')
            ->name('admin.reservations.refunds.store');
        Route::patch('/reservations/{reservation}/payment-details', [ReservationPaymentController::class, 'updateDetails'])->name('admin.reservations.payments.details');
        Route::put('/reservations/{reservation}/payments/{payment}', [ReservationPaymentController::class, 'update'])->name('admin.reservations.payments.update');
        Route::delete('/reservations/{reservation}/payments/{payment}', [ReservationPaymentController::class, 'destroy'])->name('admin.reservations.payments.destroy');
    });
    Route::get('/inquiries', [AdminController::class, 'inquiries'])->name('admin.inquiries');
    Route::get('/inquiries/{inquiry}', [AdminController::class, 'showInquiry'])->name('admin.inquiries.show');
    Route::post('/inquiries/{inquiry}/reply', [AdminController::class, 'replyToInquiry'])->name('admin.inquiries.reply');
    Route::delete('/inquiries/{inquiry}', [AdminController::class, 'destroyInquiry'])->name('admin.inquiries.destroy');
    Route::patch('/inquiries/{inquiry}/status', [AdminController::class, 'updateInquiryStatus'])->name('admin.inquiries.status');
    Route::middleware('ensure.full-admin')->group(function () {
        Route::resource('packages', AdminPackageController::class)
            ->except('show')
            ->middlewareFor(['store', 'update', 'destroy'], 'confirm.admin-password')
            ->names('admin.packages');
        Route::resource('services', AdminServiceController::class)
            ->except('show')
            ->middlewareFor(['store', 'update', 'destroy'], 'confirm.admin-password')
            ->names('admin.services');
        Route::patch('/services/{service}/toggle', [AdminServiceController::class, 'toggle'])
            ->middleware('confirm.admin-password')
            ->name('admin.services.toggle');
        Route::resource('gallery', AdminGalleryController::class)
            ->except(['show', 'create', 'edit'])
            ->middlewareFor(['store', 'update', 'destroy'], 'confirm.admin-password')
            ->names('admin.gallery');
        Route::get('/team-admins', [AdminUserController::class, 'index'])->name('admin.users');
        Route::post('/team-admins', [AdminUserController::class, 'store'])->name('admin.users.store');
        Route::put('/team-admins/{user}/reset-password', [AdminUserController::class, 'resetPassword'])->name('admin.users.reset');
        Route::get('/reports', [ReportController::class, 'index'])->name('admin.reports');
        Route::get('/reports/export/{period}', [ReportController::class, 'export'])->whereIn('period', ['daily', 'weekly', 'monthly', 'yearly'])->name('admin.reports.export');
        Route::get('/reports/export/{period}/excel', [ReportController::class, 'exportExcel'])->whereIn('period', ['daily', 'weekly', 'monthly', 'yearly'])->name('admin.reports.export.excel');
        Route::get('/analytics', [AdminController::class, 'analytics'])->name('admin.analytics');
        Route::get('/activity-logs', [ActivityLogController::class, 'index'])->name('admin.activity-logs');
    });
    Route::get('/backups', [BackupController::class, 'index'])->name('admin.backups');
    Route::post('/backups/create', [BackupController::class, 'createBackup'])->name('admin.backups.create');
    Route::post('/backups/upload', [BackupController::class, 'uploadBackup'])->name('admin.backups.upload');
    Route::post('/backups/restore', [BackupController::class, 'restoreBackup'])->name('admin.backups.restore');
    Route::post('/backups/download', [BackupController::class, 'downloadBackup'])->name('admin.backups.download');
    Route::delete('/backups', [BackupController::class, 'deleteBackup'])->name('admin.backups.delete');
});
