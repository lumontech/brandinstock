<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\OwnedByUser;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['company_id', 'first_name', 'last_name', 'job_title', 'email', 'phone', 'notes', 'marketing_consent'])]
class Contact extends Model
{
    use Auditable, HasFactory, OwnedByUser, SoftDeletes;

    protected $hidden = ['email_hash'];

    protected function casts(): array
    {
        return [
            'email' => 'encrypted',
            'phone' => 'encrypted',
            'notes' => 'encrypted',
            'marketing_consent' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (Contact $contact) {
            if ($contact->isDirty('email')) {
                $contact->email_hash = static::hashEmail($contact->email);
            }
        });
    }

    /** Indice cieco: HMAC dell'email normalizzata, permette la ricerca esatta senza salvare l'email in chiaro. */
    public static function hashEmail(?string $email): ?string
    {
        if ($email === null || trim($email) === '') {
            return null;
        }

        return hash_hmac('sha256', mb_strtolower(trim($email)), (string) config('crm.blind_index_key'));
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }
}
