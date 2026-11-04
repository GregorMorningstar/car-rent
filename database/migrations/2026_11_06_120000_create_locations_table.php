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
        Schema::create('locations', function (Blueprint $table) {
            // Identyfikator miejsca / Location ID
            $table->id();
            // Nazwa, np. Miś Krosno / Name, e.g. Miś Krosno
            $table->string('name', 150)->unique();
            // Ulica / Street
            $table->string('street', 150);
            // Numer budynku / Building number
            $table->string('building_number', 10)->nullable();
            // Kod pocztowy / Postal code
            $table->string('postal_code', 6);
            // Miejscowość / City
            $table->string('city', 100);
            // Współrzędne punktu na mapie / Map coordinates
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            // Szczegóły, np. wjazd od podwórza / Details, e.g. entrance from the yard
            $table->text('details')->nullable();
            // Czy można tu odebrać auto / Whether a car can be picked up here
            $table->boolean('is_pickup')->default(true);
            // Czy można tu zwrócić auto / Whether a car can be returned here
            $table->boolean('is_dropoff')->default(true);
            // Czy widoczne na listach wyboru / Whether it is available to choose
            $table->boolean('is_active')->default(true);
            // Data utworzenia i aktualizacji / Created at and updated at
            $table->timestamps();
        });

        Schema::create('car_location', function (Blueprint $table) {
            // Samochód / Car
            $table->foreignId('car_id')->constrained()->cascadeOnDelete();
            // Miejsce / Location
            $table->foreignId('location_id')->constrained()->cascadeOnDelete();
            // Jedno miejsce raz na samochód / Each location once per car
            $table->primary(['car_id', 'location_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('car_location');
        Schema::dropIfExists('locations');
    }
};
