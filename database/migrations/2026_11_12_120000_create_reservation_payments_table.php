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
        Schema::create('reservation_payments', function (Blueprint $table) {
            // Identyfikator wpłaty / Payment ID
            $table->id();
            // Rezerwacja / Reservation
            $table->foreignId('reservation_id')->constrained()->cascadeOnDelete();
            // Rodzaj: zaliczka lub kaucja / Type: advance or deposit
            $table->string('type', 20);
            // Od kogo wpłynęła / Who paid
            $table->string('payer', 150);
            // Kwota / Amount
            $table->decimal('amount', 10, 2);
            // Sposób wpłaty / Payment method
            $table->string('method', 30);
            // Kiedy wpłynęła, w UTC / When it was paid, in UTC
            $table->dateTime('paid_at');
            // Zwrócona kwota, 0 gdy bez zwrotu / Refunded amount, 0 when nothing was returned
            $table->decimal('refunded_amount', 10, 2)->nullable();
            // Powód zmniejszenia lub braku zwrotu / Reason for a reduced or withheld refund
            $table->string('refund_reason', 500)->nullable();
            // Kiedy rozliczono, w UTC / When it was settled, in UTC
            $table->dateTime('refunded_at')->nullable();
            // Kto wprowadził wpłatę / Who recorded the payment
            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            // Kto rozliczył / Who settled it
            $table->foreignId('refunded_by')->nullable()->constrained('users')->nullOnDelete();
            // Data utworzenia i aktualizacji / Created at and updated at
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('reservation_payments');
    }
};
