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
        Schema::create('invitations', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('theme_id')->nullable();

            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('order_id')->constrained()->onDelete('cascade');

            $table->enum('status', ['draft', 'published', 'expired'])->default('draft');
            $table->date('expiry_date')->nullable();
            $table->string('groom')->nullable();
            $table->string('bride')->nullable();
            $table->string('slug')->nullable();
            $table->timestamps();

            $table->index('user_id');
            $table->index('order_id');
            $table->index('status');
            $table->index('slug');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('invitations');
    }
};
