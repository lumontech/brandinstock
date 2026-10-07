<?php

namespace App\Support;

use Illuminate\Support\Str;

/**
 * Città scritte in modo uniforme: sigle di provincia ("ct" → Catania), maiuscole e accenti
 * ("parma" → Parma, "forli" → Forlì). Le città non in elenco restano come scritte, con l'iniziale maiuscola.
 */
final class ItalianCity
{
    /** Sigla della provincia → capoluogo. */
    public const PROVINCES = [
        'AG' => 'Agrigento',
        'AL' => 'Alessandria',
        'AN' => 'Ancona',
        'AO' => 'Aosta',
        'AR' => 'Arezzo',
        'AP' => 'Ascoli Piceno',
        'AT' => 'Asti',
        'AV' => 'Avellino',
        'BA' => 'Bari',
        'BT' => 'Barletta',
        'BL' => 'Belluno',
        'BN' => 'Benevento',
        'BG' => 'Bergamo',
        'BI' => 'Biella',
        'BO' => 'Bologna',
        'BZ' => 'Bolzano',
        'BS' => 'Brescia',
        'BR' => 'Brindisi',
        'CA' => 'Cagliari',
        'CL' => 'Caltanissetta',
        'CB' => 'Campobasso',
        'CE' => 'Caserta',
        'CT' => 'Catania',
        'CZ' => 'Catanzaro',
        'CH' => 'Chieti',
        'CO' => 'Como',
        'CS' => 'Cosenza',
        'CR' => 'Cremona',
        'KR' => 'Crotone',
        'CN' => 'Cuneo',
        'EN' => 'Enna',
        'FM' => 'Fermo',
        'FE' => 'Ferrara',
        'FI' => 'Firenze',
        'FG' => 'Foggia',
        'FC' => 'Forlì',
        'FR' => 'Frosinone',
        'GE' => 'Genova',
        'GO' => 'Gorizia',
        'GR' => 'Grosseto',
        'IM' => 'Imperia',
        'IS' => 'Isernia',
        'AQ' => 'L\'Aquila',
        'SP' => 'La Spezia',
        'LT' => 'Latina',
        'LE' => 'Lecce',
        'LC' => 'Lecco',
        'LI' => 'Livorno',
        'LO' => 'Lodi',
        'LU' => 'Lucca',
        'MC' => 'Macerata',
        'MN' => 'Mantova',
        'MS' => 'Massa',
        'MT' => 'Matera',
        'ME' => 'Messina',
        'MI' => 'Milano',
        'MO' => 'Modena',
        'MB' => 'Monza',
        'NA' => 'Napoli',
        'NO' => 'Novara',
        'NU' => 'Nuoro',
        'OR' => 'Oristano',
        'PD' => 'Padova',
        'PA' => 'Palermo',
        'PR' => 'Parma',
        'PV' => 'Pavia',
        'PG' => 'Perugia',
        'PU' => 'Pesaro',
        'PE' => 'Pescara',
        'PC' => 'Piacenza',
        'PI' => 'Pisa',
        'PT' => 'Pistoia',
        'PN' => 'Pordenone',
        'PZ' => 'Potenza',
        'PO' => 'Prato',
        'RG' => 'Ragusa',
        'RA' => 'Ravenna',
        'RC' => 'Reggio Calabria',
        'RE' => 'Reggio Emilia',
        'RI' => 'Rieti',
        'RN' => 'Rimini',
        'RM' => 'Roma',
        'RO' => 'Rovigo',
        'SA' => 'Salerno',
        'SS' => 'Sassari',
        'SV' => 'Savona',
        'SI' => 'Siena',
        'SR' => 'Siracusa',
        'SO' => 'Sondrio',
        'SU' => 'Carbonia',
        'TA' => 'Taranto',
        'TE' => 'Teramo',
        'TR' => 'Terni',
        'TO' => 'Torino',
        'TP' => 'Trapani',
        'TN' => 'Trento',
        'TV' => 'Treviso',
        'TS' => 'Trieste',
        'UD' => 'Udine',
        'VA' => 'Varese',
        'VE' => 'Venezia',
        'VB' => 'Verbania',
        'VC' => 'Vercelli',
        'VR' => 'Verona',
        'VV' => 'Vibo Valentia',
        'VI' => 'Vicenza',
        'VT' => 'Viterbo',
    ];

    /** Nomi alternativi dei capoluoghi. */
    private const ALIASES = [
        'reggio di calabria' => 'RC', 'reggio emilia' => 'RE', "reggio nell'emilia" => 'RE', 'forli cesena' => 'FC',
        'pesaro urbino' => 'PU', 'massa carrara' => 'MS', 'monza brianza' => 'MB', 'monza e brianza' => 'MB',
        'barletta andria trani' => 'BT', 'bolzano bozen' => 'BZ', 'aquila' => 'AQ', 'la aquila' => 'AQ', 'spezia' => 'SP',
        'verbano cusio ossola' => 'VB', 'sud sardegna' => 'SU', 'roma capitale' => 'RM', 'rome' => 'RM', 'milan' => 'MI',
        'naples' => 'NA', 'florence' => 'FI', 'venice' => 'VE', 'turin' => 'TO', 'genoa' => 'GE',
    ];

    /**
     * @return array{city: ?string, province: ?string} città normalizzata e sigla (se riconosciuta)
     */
    public static function normalize(?string $value): array
    {
        $value = trim((string) $value);
        if ($value === '') {
            return ['city' => null, 'province' => null];
        }
        // "Catania (CT)" o "Catania - CT": la sigla tra parentesi indica la provincia.
        $sigla = null;
        if (preg_match('/^(.+?)\s*[\(\-–]\s*([A-Za-z]{2})\s*\)?\s*$/u', $value, $m) && isset(self::PROVINCES[strtoupper($m[2])])) {
            [$value, $sigla] = [trim($m[1]), strtoupper($m[2])];
        }
        $key = self::key($value);

        $code = strlen($key) === 2 && isset(self::PROVINCES[strtoupper($key)]) ? strtoupper($key) : (self::ALIASES[$key] ?? null);
        if ($code) {
            return ['city' => self::PROVINCES[$code], 'province' => $code];
        }
        foreach (self::PROVINCES as $code => $city) {
            if (self::key($city) === $key) {
                return ['city' => $city, 'province' => $code];
            }
        }

        // Città non in elenco: solo maiuscole sistemate ("san giovanni la punta" → "San Giovanni La Punta").
        $pretty = mb_strtolower($value) === $value || mb_strtoupper($value) === $value ? Str::title(mb_strtolower($value)) : $value;

        return ['city' => Str::limit($pretty, 100, ''), 'province' => $sigla];
    }

    private static function key(string $value): string
    {
        return Str::of($value)->lower()->ascii()->replaceMatches('/[^a-z0-9\']+/', ' ')->replace("'", ' ')->squish()->value();
    }
}
