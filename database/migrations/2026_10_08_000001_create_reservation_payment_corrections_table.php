<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reservation_payment_corrections', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('reservation_id');
            $table->unsignedBigInteger('payment_id');
            $table->unsignedBigInteger('admin_user_id')->nullable();
            $table->string('admin_name');
            $table->string('admin_email')->nullable();
            $table->text('reason');
            $table->json('original_values');
            $table->json('corrected_values');
            $table->boolean('ocr_used')->default(false);
            $table->string('receipt_validation_result', 20)->default('not_replaced');
            $table->string('duplicate_check_result', 20)->default('passed');
            $table->timestamps();

            $table->index('reservation_id', 'rpc_reservation_idx');
            $table->index(['payment_id', 'created_at'], 'rpc_payment_created_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reservation_payment_corrections');
    }
};
