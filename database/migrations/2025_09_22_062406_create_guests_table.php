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
        Schema::create('guests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('invitation_id')->constrained()->onDelete('cascade');
            $table->string('name');
            $table->string('slug')->unique()->nullable();
            $table->string('phone')->nullable();
            $table->boolean('is_group')->default(false);
            $table->enum('attendance_status', ['pending', 'attending', 'not_attending'])->default('pending');
            $table->timestamps();

            $table->index(['invitation_id', 'attendance_status']);
            $table->index('slug');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('guests');
    }
};
