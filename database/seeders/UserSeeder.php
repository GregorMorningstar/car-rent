<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * Konta startowe: administrator i użytkownik, z danymi adresowymi (user_profiles).
 * Ponowne uruchomienie aktualizuje istniejące konta (po adresie e-mail) zamiast tworzyć duplikaty.
 */
class UserSeeder extends Seeder
{
    public function run(): void
    {
        $accounts = [
            [
                'name' => 'Administrator',
                'email' => 'admin_kwsmis@mis.pl',
                'role' => UserRole::Administrator,
                'profile' => [
                    'phone' => '+48 600 100 200',
                    'street' => 'ks. Jerzego Popiełuszki',
                    'building_number' => '1',
                    'apartment_number' => null,
                    'postal_code' => '38-400',
                    'city' => 'Krosno',
                ],
            ],
            [
                'name' => 'Użytkownik',
                'email' => 'user_kwsmis@mis.pl',
                'role' => UserRole::User,
                'profile' => [
                    'phone' => '+48 600 300 400',
                    'street' => 'Podkarpacka',
                    'building_number' => '12',
                    'apartment_number' => '5',
                    'postal_code' => '38-400',
                    'city' => 'Krosno',
                ],
            ],
        ];

        foreach ($accounts as $account) {
            $user = User::query()->firstOrNew(['email' => $account['email']]);

            $user->forceFill([
                'name' => $account['name'],
                'password' => Hash::make('qwer1234'),
                'role' => $account['role'],
                'email_verified_at' => now(),
            ])->save();

            $user->profile()->updateOrCreate([], $account['profile']);
        }
    }
}
