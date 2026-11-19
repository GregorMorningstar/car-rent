<?php

/*
|--------------------------------------------------------------------------
| Czas przechowywania danych
|--------------------------------------------------------------------------
|
| Komenda data:prune czyści konta po tych terminach. Rezerwacje, wpłaty, dane
| kierowców i rozmowy aktywnych kont zostają. Wartości trzeba uzgodnić
| z prawnikiem i księgowością, a potem opisać w polityce prywatności.
|
*/

return [
    // Konto bez potwierdzonego e-maila i bez rezerwacji
    'unverified_account_days' => (int) env('RETENTION_UNVERIFIED_ACCOUNT_DAYS', 30),

    // Konto bez logowania
    'inactive_account_months' => (int) env('RETENTION_INACTIVE_ACCOUNT_MONTHS', 36),
];
