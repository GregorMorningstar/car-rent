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
        Schema::create('car_equipment', function (Blueprint $table) {
            // Samochód / Car
            $table->foreignId('car_id')->constrained()->cascadeOnDelete();
            // Wyposażenie / Equipment
            $table->foreignId('equipment_id')->constrained('equipment')->cascadeOnDelete();
            // Jedno wyposażenie raz na samochód / Each equipment once per car
            $table->primary(['car_id', 'equipment_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('car_equipment');
    }
};
