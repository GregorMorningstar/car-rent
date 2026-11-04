<?php

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
            // Data rozpoczęcia wynajmu (od) / Rental start date (from)
            $table->date('start_date');
            // Data zakończenia wynajmu (do) / Rental end date (to)
            $table->date('end_date');
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
            // Data utworzenia i aktualizacji / Created at and updated at
            $table->timestamps();

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
