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
        Schema::create('car_prices', function (Blueprint $table) {
            // Identyfikator progu / Price tier ID
            $table->id();
            // Samochód / Car
            $table->foreignId('car_id')->constrained()->cascadeOnDelete();
            // Od ilu dni wynajmu / Minimum rental days
            $table->unsignedSmallInteger('min_days');
            // Do ilu dni, pusty to bez limitu / Maximum rental days, empty means no limit
            $table->unsignedSmallInteger('max_days')->nullable();
            // Cena za dobę w tym progu / Daily rate in this tier
            $table->decimal('daily_rate', 10, 2);
            // Data utworzenia i aktualizacji / Created at and updated at
            $table->timestamps();

            // Jeden próg od danej liczby dni / One tier per starting day count
            $table->unique(['car_id', 'min_days']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('car_prices');
    }
};
