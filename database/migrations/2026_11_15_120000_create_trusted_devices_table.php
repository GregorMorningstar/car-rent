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
        Schema::create('trusted_devices', function (Blueprint $table) {
            // Identyfikator urządzenia / Device ID
            $table->id();
            // Właściciel / Owner
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            // Skrót tokenu z ciasteczka, sam token nie trafia do bazy / Hash of the cookie token
            $table->string('token_hash', 64)->unique();
            // Opis urządzenia, np. Chrome na Windows / Device label
            $table->string('name', 100);
            // Ostatnie użycie, w UTC / Last used at, in UTC
            $table->dateTime('last_used_at');
            // Koniec zaufania, w UTC / Trust ends at, in UTC
            $table->dateTime('expires_at')->index();
            // Data utworzenia i aktualizacji / Created at and updated at
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('trusted_devices');
    }
};
