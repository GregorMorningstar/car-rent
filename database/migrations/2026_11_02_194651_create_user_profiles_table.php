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
        Schema::create('user_profiles', function (Blueprint $table) {
            // Identyfikator profilu / Profile ID
            $table->id();
            // Użytkownik (relacja 1:1) / User (one-to-one relation)
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            // Numer telefonu / Phone number
            $table->string('phone', 20)->unique();
            // Ulica / Street
            $table->string('street', 150);
            // Numer budynku / Building number
            $table->string('building_number', 10);
            // Numer lokalu / Apartment number
            $table->string('apartment_number', 10)->nullable();
            // Kod pocztowy, np. 00-001 / Postal code, e.g. 00-001
            $table->string('postal_code', 10);
            // Miejscowość / City
            $table->string('city', 100);
            // Data utworzenia i aktualizacji / Created at and updated at
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('user_profiles');
    }
};
