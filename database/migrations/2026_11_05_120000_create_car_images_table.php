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
        Schema::create('car_images', function (Blueprint $table) {
            // Identyfikator zdjęcia / Image ID
            $table->id();
            // Samochód / Car
            $table->foreignId('car_id')->constrained()->cascadeOnDelete();
            // Ścieżka na dysku "public" / Path on the "public" disk
            $table->string('path');
            // Kolejność, pierwsze zdjęcie to okładka / Order, the first image is the cover
            $table->unsignedSmallInteger('position')->default(0);
            // Data utworzenia i aktualizacji / Created at and updated at
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('car_images');
    }
};
