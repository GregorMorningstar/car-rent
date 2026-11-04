<?php

use App\Enums\CarStatus;
use App\Enums\FuelType;
use App\Enums\Transmission;
use App\Enums\VehicleType;
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
        Schema::create('cars', function (Blueprint $table) {
            // Identyfikator samochodu / Car ID
            $table->id();
            // Marka, np. Toyota / Brand, e.g. Toyota
            $table->string('brand', 100);
            // Model, np. Corolla / Model, e.g. Corolla
            $table->string('model', 100);
            // Rodzaj pojazdu: passenger, van, bus, tow_truck / Vehicle type
            $table->enum('vehicle_type', VehicleType::values())->default(VehicleType::Passenger->value);
            // Numer rejestracyjny / Registration number
            $table->string('registration_number', 20)->unique();
            // Numer VIN / VIN number
            $table->string('vin', 17)->unique();
            // Rok produkcji / Production year
            $table->smallInteger('production_year');
            // Rodzaj paliwa: petrol, diesel, hybrid, electric / Fuel type
            $table->enum('fuel_type', FuelType::values());
            // Skrzynia biegów: manual, automatic / Transmission
            $table->enum('transmission', Transmission::values());
            // Liczba miejsc / Number of seats
            $table->smallInteger('seats');
            // Liczba drzwi / Number of doors
            $table->smallInteger('doors');
            // Aktualny przebieg (km) / Current mileage (km)
            $table->integer('mileage_km');
            // Kolor / Color
            $table->string('color', 50)->nullable();
            // Najniższa cena za dobę z progów w car_prices / Lowest daily rate from car_prices tiers
            $table->decimal('daily_rate', 10, 2);
            // Limit kilometrów na dobę, pusty to bez limitu / Daily mileage limit, empty means unlimited
            $table->unsignedInteger('km_limit_per_day')->nullable();
            // Wymagana kaucja / Required deposit
            $table->decimal('deposit_amount', 10, 2);
            // Status: active, maintenance, retired / Operational status
            $table->enum('operational_status', CarStatus::values())->default(CarStatus::Active->value);
            // Opis oferty / Offer description
            $table->text('description')->nullable();
            // Data utworzenia i aktualizacji / Created at and updated at
            $table->timestamps();
            // Opcjonalne usunięcie logiczne / Soft delete timestamp
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('cars');
    }
};
