<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('reservation_payments', function (Blueprint $table): void {
            $table->string('reference_number', 100)->nullable();
            $table->char('receipt_sha256', 64)->nullable()->unique();
            $table->char('reference_sha256', 64)->nullable()->unique();
        });

        $disk = Storage::disk('local');
        $seenHashes = [];

        DB::table('reservation_payments')
            ->whereNotNull('receipt_image_path')
            ->orderBy('id')
            ->get(['id', 'receipt_image_path'])
            ->each(function (object $payment) use ($disk, &$seenHashes): void {
                $path = $payment->receipt_image_path;
                if (! is_string($path) || ! str_starts_with($path, 'payment-receipts/') || ! $disk->exists($path)) {
                    return;
                }

                $hash = hash_file('sha256', $disk->path($path));
                if ($hash === false || isset($seenHashes[$hash])) {
                    return;
                }

                DB::table('reservation_payments')
                    ->where('id', $payment->id)
                    ->update(['receipt_sha256' => $hash]);
                $seenHashes[$hash] = true;
            });
    }

    public function down(): void
    {
        Schema::table('reservation_payments', function (Blueprint $table): void {
            $table->dropUnique('reservation_payments_receipt_sha256_unique');
            $table->dropUnique('reservation_payments_reference_sha256_unique');
            $table->dropColumn(['reference_number', 'receipt_sha256', 'reference_sha256']);
        });
    }
};
