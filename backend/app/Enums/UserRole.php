<?php

namespace App\Enums;

enum UserRole: string
{
    case Admin = 'admin';
    case Manager = 'manager';
    case Sales = 'sales';

    public function label(): string
    {
        return match ($this) {
            self::Admin => 'Amministratore',
            self::Manager => 'Responsabile vendite',
            self::Sales => 'Venditore',
        };
    }

    /** Può vedere e gestire i dati di tutti i venditori. */
    public function seesEverything(): bool
    {
        return $this !== self::Sales;
    }
}
