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
        Schema::create('themes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('theme_category_id')->constrained()->onDelete('cascade');
            $table->string('name');
            $table->string('link')->nullable();
            $table->string('thumbnail')->nullable();
            $table->boolean('is_premium')->default(false);
            $table->timestamps();

            $table->index('theme_category_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('themes');
    }
};
