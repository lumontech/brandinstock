<?php

namespace App\Support;

/**
 * Ripara le note dei lead importati prima della correzione dell'import, che univa tutte le righe
 * in una sola ("Nome e Cognome: Mario Rossi Stato: Prospect Creato: …"). Le divide di nuovo
 * riconoscendo le etichette delle colonne di Airtable.
 */
final class FlattenedNotes
{
    /** Etichette che l'import scriveva nelle note (colonne di Airtable e note aggiunte dal CRM). */
    private const LABELS = [
        'Nome e Cognome', 'Stato', 'Disponibilià economica', 'Disponibilità economica', 'Hai altri negozi?',
        'Vendi già abbigliamento?', 'Provenienza Leads', 'Da contattare', 'Appuntamento', 'Esito', 'Interessato a',
        'Rating', 'Data creazione', 'Città2', 'Messaggio', 'Preventivi', 'Creato', 'Azienda', 'Email',
        'Numero di telefono', 'Città', 'Note', 'Provenienza', 'Email (non valida)', 'Telefono',
    ];

    /** Righe di sistema di Airtable da non tenere nelle note. */
    private const DROP = ['Creato'];

    /**
     * @return array{notes: ?string, fields: array<string, string>}|null null se le note non vanno riparate
     */
    public static function split(string $notes): ?array
    {
        if (str_contains($notes, "\n")) {
            return null;
        }
        $labels = self::LABELS;
        usort($labels, fn ($a, $b) => mb_strlen($b) <=> mb_strlen($a));
        $pattern = '/(?:^|\s)('.implode('|', array_map(fn ($l) => preg_quote($l, '/'), $labels)).'):\s/u';
        if (! preg_match_all($pattern, $notes, $matches, PREG_OFFSET_CAPTURE) || count($matches[0]) < 2) {
            return null;
        }

        $lines = [];
        $fields = [];
        $first = trim(substr($notes, 0, $matches[0][0][1]));
        if ($first !== '') {
            $lines[] = $first;
        }
        foreach ($matches[1] as $i => [$label, $offset]) {
            $start = $offset + strlen($label) + 1;
            $end = $matches[0][$i + 1][1] ?? strlen($notes);
            $value = trim(substr($notes, $start, $end - $start));
            if ($value === '') {
                continue;
            }
            $fields[$label] ??= $value;
            if (! in_array($label, self::DROP, true)) {
                $lines[] = "{$label}: {$value}";
            }
        }

        return ['notes' => $lines ? implode("\n", $lines) : null, 'fields' => $fields];
    }
}
