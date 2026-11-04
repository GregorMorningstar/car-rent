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
        Schema::create('colors', function (Blueprint $table) {
            // Identyfikator koloru / Color ID
            $table->id();
            // Nazwa, np. Biały / Name, e.g. White
            $table->string('name', 50)->unique();
            // Wzór koloru, np. #ffffff / Color swatch, e.g. #ffffff
            $table->char('hex', 7)->default('#9ca3af');
            // Czy widoczny na liście do wyboru / Whether it is available to choose
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
        Schema::dropIfExists('colors');
    }
};
