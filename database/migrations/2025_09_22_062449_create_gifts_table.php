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
        Schema::create('gifts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('invitation_id')->constrained()->onDelete('cascade');
            $table->string('bank_name', 100);
            $table->string('account_number', 50);
            $table->string('account_holder', 100);
            $table->timestamps();

            $table->index('invitation_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('gifts');
    }
};
