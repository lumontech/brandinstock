<?php

namespace App\Models\Concerns;

use App\Models\AuditLog;
use Illuminate\Database\Eloquent\Model;

/**
 * Registra creazione, modifica ed eliminazione del modello in audit_logs.
 * Per i campi cifrati viene salvato solo il nome del campo, mai il valore.
 */
trait Auditable
{
    public static function bootAuditable(): void
    {
        static::created(fn (Model $model) => $model->writeAudit('created', $model->auditValues(array_keys($model->getAttributes()))));
        static::updated(function (Model $model) {
            $changed = array_diff(array_keys($model->getChanges()), ['updated_at']);
            if ($changed) {
                $model->writeAudit('updated', $model->auditValues($changed));
            }
        });
        static::deleted(fn (Model $model) => $model->writeAudit('deleted'));
    }

    /** @param  list<string>  $keys */
    protected function auditValues(array $keys): array
    {
        $hidden = array_merge($this->getHidden(), ['password', 'remember_token', 'created_at', 'updated_at']);
        $encrypted = array_keys(array_filter($this->getCasts(), fn ($cast) => str_starts_with((string) $cast, 'encrypted')));
        $values = [];

        foreach ($keys as $key) {
            if (in_array($key, $hidden, true)) {
                continue;
            }
            $values[$key] = in_array($key, $encrypted, true) ? '[cifrato]' : $this->getAttribute($key);
        }

        return $values;
    }

    protected function writeAudit(string $event, ?array $changes = null): void
    {
        AuditLog::record($event, $this, $changes);
    }
}
