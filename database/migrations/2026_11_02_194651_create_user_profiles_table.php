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
            // Dane poniżej są szyfrowane, stąd typ text / Fields below are encrypted, hence text
            // Numer telefonu / Phone number
            $table->text('phone');
            // Ulica / Street
            $table->text('street');
            // Numer budynku / Building number
            $table->text('building_number');
            // Numer lokalu / Apartment number
            $table->text('apartment_number')->nullable();
            // Kod pocztowy, np. 00-001 / Postal code, e.g. 00-001
            $table->text('postal_code');
            // Miejscowość / City
            $table->text('city');
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
