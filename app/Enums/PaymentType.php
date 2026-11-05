<?php

namespace App\Enums;

/**
 * Rodzaj wpłaty przy rezerwacji. Zaliczka to część ceny, kaucja to zabezpieczenie.
 */
enum PaymentType: string
{
    case Advance = 'advance';
    case Deposit = 'deposit';
}
