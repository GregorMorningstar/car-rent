<?php

namespace App\Console\Commands;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

/**
 * Pierwszy administrator na produkcji, bez hasła zapisanego w kodzie.
 */
#[Signature('admin:create {email : Adres e-mail} {name : Imię i nazwisko}')]
#[Description('Tworzy konto administratora z hasłem podanym w konsoli')]
class CreateAdministrator extends Command
{
    public function handle(): int
    {
        $data = [
            'email' => $this->argument('email'),
            'name' => $this->argument('name'),
            'password' => $this->secret('Hasło'),
        ];

        $validator = Validator::make($data, [
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', Rule::unique(User::class)],
            'name' => ['required', 'string', 'max:255'],
            'password' => ['required', 'string', Password::defaults()],
        ]);

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $error) {
                $this->error($error);
            }

            return self::FAILURE;
        }

        (new User)->forceFill([
            ...$validator->validated(),
            'role' => UserRole::Administrator,
            'email_verified_at' => now(),
        ])->save();

        $this->info('Konto administratora zostało utworzone.');

        return self::SUCCESS;
    }
}
