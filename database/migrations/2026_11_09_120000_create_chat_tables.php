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
        Schema::create('conversations', function (Blueprint $table) {
            // Identyfikator rozmowy / Conversation ID
            $table->id();
            // Klient - jedna rozmowa z administracją / Client - one conversation with the staff
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            // Czas ostatniej wiadomości / Last message time
            $table->timestamp('last_message_at')->nullable()->index();
            // Data utworzenia i aktualizacji / Created at and updated at
            $table->timestamps();
        });

        Schema::create('chat_messages', function (Blueprint $table) {
            // Identyfikator wiadomości / Message ID
            $table->id();
            // Rozmowa / Conversation
            $table->foreignId('conversation_id')->constrained()->cascadeOnDelete();
            // Nadawca: klient albo administrator / Sender: client or administrator
            $table->foreignId('sender_id')->constrained('users')->cascadeOnDelete();
            // Treść / Body
            $table->text('body');
            // Odczytano przez drugą stronę / Read by the other side
            $table->timestamp('read_at')->nullable();
            // Przekazano na Messengera / Forwarded to Messenger
            $table->timestamp('messenger_forwarded_at')->nullable();
            // Data utworzenia i aktualizacji / Created at and updated at
            $table->timestamps();
        });

        Schema::create('settings', function (Blueprint $table) {
            // Nazwa ustawienia / Setting name
            $table->string('key', 100)->primary();
            // Zaszyfrowana wartość / Encrypted value
            $table->text('value')->nullable();
            // Data utworzenia i aktualizacji / Created at and updated at
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('settings');
        Schema::dropIfExists('chat_messages');
        Schema::dropIfExists('conversations');
    }
};
