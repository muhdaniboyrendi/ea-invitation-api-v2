<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('package_id')->constrained()->onDelete('restrict');
            $table->string('order_id')->unique();
            $table->decimal('amount', 10, 2);
            $table->enum('payment_status', ['pending', 'paid', 'canceled', 'expired'])->default('pending');
            $table->string('payment_method')->nullable();
            $table->string('snap_token')->nullable();
            $table->string('midtrans_url')->nullable();
            $table->string('midtrans_transaction_id')->nullable();
            $table->timestamps();

            $table->index('user_id');
            $table->index('package_id');
            $table->index('order_id');
            $table->index('payment_status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};
