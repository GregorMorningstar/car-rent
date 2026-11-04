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
        Schema::create('equipment', function (Blueprint $table) {
            // Identyfikator wyposażenia / Equipment ID
            $table->id();
            // Nazwa, np. Klimatyzacja / Name, e.g. Air conditioning
            $table->string('name', 100)->unique();
            // Ikona Font Awesome, np. snowflake / Font Awesome icon, e.g. snowflake
            $table->string('icon', 100)->nullable();
            // Kształt ikony spoza zestawu wbudowanego / Shape of an icon outside the built-in set
            $table->json('icon_svg')->nullable();
            // Czy widoczne na liście do wyboru / Whether it is available to choose
            $table->boolean('is_active')->default(true);
            // Data utworzenia i aktualizacji / Created at and updated at
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('equipment');
    }
};
