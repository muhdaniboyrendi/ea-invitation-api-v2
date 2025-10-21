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
        Schema::create('events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('invitation_id')->constrained()->onDelete('cascade');
            $table->string('name');
            $table->string('venue');
            $table->date('date');
            $table->time('time_start');
            $table->text('address')->nullable();
            $table->text('maps_url')->nullable();
            $table->text('maps_embed_url')->nullable();
            $table->timestamps();

            $table->index(['invitation_id', 'date']);
            $table->index(['date', 'time_start']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('events');
    }
};
