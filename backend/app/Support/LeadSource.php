<?php

namespace App\Support;

use Illuminate\Support\Str;

/** Provenienza dei lead: elenco unico per clienti e opportunità (le etichette sono nel frontend). */
final class LeadSource
{
    public const ALL = [
        'sito', 'google', 'social', 'linkedin', 'fiera', 'passaparola', 'email',
        'cold_call', 'whatsapp', 'agente', 'cliente_esistente', 'altro',
    ];

    /** Parole con cui la provenienza compare di solito negli export (es. Airtable). */
    private const ALIASES = [
        'sito' => ['sito', 'sito web', 'website', 'web', 'form', 'modulo contatti', 'landing', 'landing page', 'ecommerce', 'e-commerce'],
        'google' => ['google', 'google ads', 'adwords', 'sem', 'seo', 'ricerca google', 'organico'],
        'social' => ['social', 'facebook', 'instagram', 'meta', 'meta ads', 'fb', 'ig', 'tiktok', 'social network'],
        'linkedin' => ['linkedin', 'linked in'],
        'fiera' => ['fiera', 'fiere', 'evento', 'eventi', 'expo', 'trade show', 'pitti', 'micam', 'white'],
        'passaparola' => ['passaparola', 'referral', 'referenza', 'consiglio', 'conoscenza', 'amico'],
        'email' => ['email', 'e-mail', 'mail', 'newsletter', 'dem', 'mailing'],
        'cold_call' => ['cold call', 'cold calling', 'telefonata', 'chiamata', 'telemarketing', 'telefono'],
        'whatsapp' => ['whatsapp', 'whats app', 'wa'],
        'agente' => ['agente', 'agenti', 'segnalatore', 'segnalazione', 'rappresentante', 'procacciatore'],
        'cliente_esistente' => ['cliente esistente', 'cliente', 'già cliente', 'gia cliente', 'riacquisto', 'fidelizzato'],
        'altro' => ['altro', 'other', 'altra'],
    ];

    /** Converte un testo libero nella chiave corrispondente, o null se non riconosciuto. */
    public static function match(?string $value): ?string
    {
        if ($value === null || trim($value) === '') {
            return null;
        }
        $needle = Str::of($value)->lower()->ascii()->replaceMatches('/[^a-z0-9]+/', ' ')->trim()->value();
        if (in_array(str_replace(' ', '_', $needle), self::ALL, true)) {
            return str_replace(' ', '_', $needle);
        }
        // Prima le corrispondenze esatte, poi quelle contenute nel testo (es. "Campagna Meta Ads").
        foreach ([false, true] as $partial) {
            foreach (self::ALIASES as $key => $aliases) {
                foreach ($aliases as $alias) {
                    $alias = Str::of($alias)->ascii()->value();
                    if ($partial ? str_contains(" {$needle} ", " {$alias} ") : $needle === $alias) {
                        return $key;
                    }
                }
            }
        }

        return null;
    }
}
