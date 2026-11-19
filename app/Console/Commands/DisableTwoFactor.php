<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Repositories\Contracts\UserSecurityRepositoryInterface;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

/**
 * Pomoc dla kogoś, kto stracił dostęp do skrzynki. Wymaga dostępu do serwera.
 */
#[Signature('two-factor:disable {email : Adres e-mail konta}')]
#[Description('Wyłącza logowanie dwuetapowe konta, które straciło dostęp do poczty')]
class DisableTwoFactor extends Command
{
    public function handle(UserSecurityRepositoryInterface $security): int
    {
        $user = User::query()->where('email', $this->argument('email'))->first();

        if ($user === null) {
            $this->error('Nie ma takiego konta.');

            return self::FAILURE;
        }

        $security->disableTwoFactor($user);
        $security->removeTrustedDevices($user->id);

        $this->info('Logowanie dwuetapowe zostało wyłączone.');

        return self::SUCCESS;
    }
}
