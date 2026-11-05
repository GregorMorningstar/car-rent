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
        Schema::create('audit_logs', function (Blueprint $table) {
            // Identyfikator wpisu / Entry ID
            $table->id();
            // Kto wykonał działanie / Who performed the action
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            // Nazwa działania / Action name
            $table->string('action', 100)->index();
            // Identyfikatory z adresu, bez treści formularza / Route ids, never the form content
            $table->string('subject', 100)->nullable();
            // Adres IP / IP address
            $table->string('ip_address', 45)->nullable();
            // Czy działanie się udało / Whether the action succeeded
            $table->boolean('succeeded');
            // Kiedy, w UTC / When, in UTC
            $table->dateTime('created_at')->index();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
    }
};
