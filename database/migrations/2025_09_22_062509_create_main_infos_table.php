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
        Schema::create('main_infos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('invitation_id')->constrained()->onDelete('cascade');
            $table->unsignedBigInteger('music_id')->nullable();
            
            $table->string('main_photo')->nullable();
            $table->date('wedding_date');
            $table->time('wedding_time');
            $table->enum('time_zone', ['WIB', 'WITA', 'WIT'])->default('WIB');
            $table->string('custom_backsound')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('main_infos');
    }
};
