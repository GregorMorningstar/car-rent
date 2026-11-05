<?php

use App\Enums\DrivingLicense;
use App\Enums\ReservationStatus;
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
        Schema::create('reservations', function (Blueprint $table) {
            // Identyfikator rezerwacji / Reservation ID
            $table->id();
            // Klient składający rezerwację / Customer who made the reservation
            $table->foreignId('user_id')->constrained();
            // Rezerwowany samochód / Reserved car
            $table->foreignId('car_id')->constrained();
            // Miejsce odbioru / Pickup location
            $table->foreignId('pickup_location_id')->nullable()->constrained('locations')->nullOnDelete();
            // Miejsce zwrotu / Dropoff location
            $table->foreignId('dropoff_location_id')->nullable()->constrained('locations')->nullOnDelete();
            // Data i godzina odbioru w UTC / Pickup date and time in UTC
            $table->dateTime('start_date');
            // Data i godzina zwrotu w UTC / Return date and time in UTC
            $table->dateTime('end_date');
            // Status: pending, confirmed, cancelled, expired / Reservation status
            $table->enum('status', ReservationStatus::values())->default(ReservationStatus::Pending->value);
            // Cena za dobę w chwili rezerwacji / Daily rate at the time of booking
            $table->decimal('daily_rate', 10, 2);
            // Kaucja w chwili rezerwacji / Deposit at the time of booking
            $table->decimal('deposit_amount', 10, 2);
            // Łączna cena wynajmu / Total rental price
            $table->decimal('total_price', 10, 2);
            // Termin potwierdzenia (np. 24h robocze, bez weekendów i świąt) / Confirmation deadline (e.g. 24 business hours, excluding weekends and holidays)
            $table->dateTime('confirmation_deadline');
            // Data potwierdzenia / Confirmed at
            $table->dateTime('confirmed_at')->nullable();
            // Data anulowania / Cancelled at
            $table->dateTime('cancelled_at')->nullable();
            // Uwagi klienta / Customer notes
            $table->text('notes')->nullable();
            // Jak klient zaakceptował zmianę auta, miejsca lub terminu przez administrację / How the client accepted the staff change
            $table->string('term_accepted_via', 30)->nullable();
            // Kiedy wpisano akceptację, w UTC / When the acceptance was recorded, in UTC
            $table->dateTime('term_accepted_at')->nullable();
            // Kto wpisał akceptację / Who recorded the acceptance
            $table->foreignId('term_accepted_by')->nullable()->constrained('users')->nullOnDelete();
            // Kto prowadzi / Who drives
            $table->string('driver_name', 150)->nullable();
            // Zaszyfrowany numer prawa jazdy kierowcy / Encrypted driver's licence number
            $table->text('driver_license_number')->nullable();
            // Kategoria prawa jazdy kierowcy / Driver's licence category
            $table->enum('driver_license_category', DrivingLicense::values())->nullable();
            // Wydanie samochodu klientowi, w UTC / Car handed over to the client, in UTC
            $table->dateTime('picked_up_at')->nullable();
            // Przyjęcie samochodu po wynajmie, w UTC / Car taken back after the rental, in UTC
            $table->dateTime('returned_at')->nullable();
            // Przebieg przy zwrocie (km) / Mileage at return (km)
            $table->unsignedInteger('returned_mileage_km')->nullable();
            // Administrator zobaczył nowe zamówienie / Administrator has seen the new order
            $table->timestamp('seen_by_admin_at')->nullable()->index();
            // Administrator zobaczył, że klient usunął rezerwację / Administrator has seen the removal
            $table->timestamp('removal_seen_by_admin_at')->nullable();
            // Data utworzenia i aktualizacji / Created at and updated at
            $table->timestamps();
            // Usunięcie przez klienta - rekord zostaje w bazie / Removed by the client - the record stays
            $table->softDeletes();

            // Szybkie sprawdzanie dostępności auta / Fast car availability checks
            $table->index(['car_id', 'start_date', 'end_date']);
            // Wyszukiwanie przeterminowanych rezerwacji / Finding expired reservations
            $table->index(['status', 'confirmation_deadline']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('reservations');
    }
};
