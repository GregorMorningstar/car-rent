<?php

namespace App\Console\Commands;

use App\Services\AccountService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

/**
 * Czyści konta po terminach z config/retention.php.
 */
#[Signature('data:prune {--dry-run : Tylko policz, co zostałoby usunięte}')]
#[Description('Usuwa porzucone konta i anonimizuje nieaktywne')]
class PruneData extends Command
{
    public function handle(AccountService $accounts): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $result = $accounts->prune($dryRun);

        $this->info(($dryRun ? 'Do usunięcia' : 'Usunięto').sprintf(
            ': konta niepotwierdzone %d, konta nieaktywne %d.',
            $result['abandoned'],
            $result['inactive'],
        ));

        return self::SUCCESS;
    }
}
