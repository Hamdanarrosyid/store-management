<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('sale_items', function (Blueprint $table) {
            $table->id();

            $table->foreignId('sale_id')
                ->constrained('sales')
                ->cascadeOnDelete();

            $table->foreignId('product_id')
                ->constrained('products')
                ->restrictOnDelete();

            // Snapshot agar histori transaksi tidak berubah meski produk diubah
            $table->string('product_name_snapshot', 200);
            $table->decimal('unit_price', 14, 2);

            $table->integer('quantity')->default(1);
            $table->decimal('line_total', 14, 2);

            $table->timestamps();

            // Index untuk query detail transaksi
            $table->index(['sale_id']);
            $table->index(['product_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sale_items');
    }
};
