<?php

namespace App\Support;

use Illuminate\Support\Str;

/** Stato di lavorazione di un lead (le etichette sono nel frontend). */
final class LeadStatus
{
    public const ALL = [
        'nuovo', 'da_richiamare', 'email_inviata', 'appuntamento', 'in_attesa', 'qualificato', 'prospect', 'non_interessato',
    ];

    /** Parole con cui lo stato compare negli export (es. il campo "Stato" di Airtable). */
    private const ALIASES = [
        'nuovo' => ['nuovo', 'nuova', 'nuova leads', 'nuovo lead', 'nuova lead', 'new', 'da contattare'],
        'da_richiamare' => ['da richiamare', 'richiamare', 'da ricontattare', 'callback'],
        'email_inviata' => ['inviata email', 'email inviata', 'mail inviata', 'inviata mail'],
        'appuntamento' => ['da prendere appuntamento', 'appuntamento', 'appuntamento fissato'],
        'in_attesa' => ['in attesa', 'attesa', 'in sospeso', 'sospeso'],
        'qualificato' => ['qualificato', 'qualificata', 'qualified'],
        'prospect' => ['prospect', 'potenziale'],
        'non_interessato' => ['non interessato', 'non interessata', 'perso', 'not interested'],
    ];

    public static function match(?string $value): ?string
    {
        if ($value === null || trim($value) === '') {
            return null;
        }
        $needle = Str::of($value)->lower()->ascii()->replaceMatches('/[^a-z0-9]+/', ' ')->trim()->value();
        if (in_array(str_replace(' ', '_', $needle), self::ALL, true)) {
            return str_replace(' ', '_', $needle);
        }
        foreach (self::ALIASES as $key => $aliases) {
            if (in_array($needle, $aliases, true)) {
                return $key;
            }
        }

        return null;
    }
}
