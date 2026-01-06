<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('sales', function (Blueprint $table) {
            $table->id();

            $table->foreignId('store_id')
                ->constrained('stores')
                ->cascadeOnDelete();

            $table->string('invoice_no', 80)->unique();

            // Kasir/admin yang membuat transaksi
            $table->foreignId('cashier_user_id')
                ->constrained('users')
                ->restrictOnDelete();

            // PAID / CANCELLED (simpan string)
            $table->string('status', 20);

            $table->decimal('subtotal', 14, 2);
            $table->decimal('discount', 14, 2)->default(0);
            $table->decimal('tax', 14, 2)->default(0);
            $table->decimal('total', 14, 2);

            $table->decimal('paid_amount', 14, 2)->default(0);
            $table->decimal('change_amount', 14, 2)->default(0);

            $table->string('payment_method', 30)->nullable(); // CASH/TRANSFER/etc
            $table->timestamp('paid_at')->nullable();

            $table->timestamps();

            // Index untuk pagination + pencarian per toko
            $table->index(['store_id', 'created_at']);
            $table->index(['store_id', 'status']);
            $table->index(['cashier_user_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sales');
    }
};
