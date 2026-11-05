<?php

namespace Database\Seeders;

use App\Models\Conversation;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Przykładowa rozmowa konta użytkownika z administracją. Ostatnia wiadomość czeka na odpowiedź.
 */
class ChatSeeder extends Seeder
{
    public function run(): void
    {
        $client = User::query()->where('email', 'user_kwsmis@mis.pl')->first();
        $staff = User::query()->where('email', 'admin_kwsmis@mis.pl')->first();

        if ($client === null || $staff === null) {
            return;
        }

        Conversation::query()->where('user_id', $client->id)->delete();

        $conversation = Conversation::query()->create(['user_id' => $client->id]);

        $messages = [
            [$client, 'Dzień dobry, czy auto można odebrać wcześniej rano?', now()->subHours(3), now()->subHours(2)],
            [$staff, 'Dzień dobry, tak, odbiór jest możliwy od 7:00.', now()->subHours(2), now()->subHour()],
            [$client, 'Dziękuję. Czy mogę też dodać fotelik dziecięcy?', now()->subMinutes(20), null],
        ];

        foreach ($messages as [$sender, $body, $sentAt, $readAt]) {
            $message = $conversation->messages()->create([
                'sender_id' => $sender->id,
                'body' => $body,
                'read_at' => $readAt,
            ]);

            $message->forceFill(['created_at' => $sentAt, 'updated_at' => $sentAt])->save();
        }

        $conversation->update(['last_message_at' => now()->subMinutes(20)]);
    }
}
