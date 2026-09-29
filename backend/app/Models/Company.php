<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\OwnedByUser;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['name', 'segment', 'vat_number', 'tax_code', 'type', 'city', 'province', 'country', 'address', 'email', 'phone', 'website', 'notes', 'source',
    'billing_name', 'billing_address', 'billing_zip', 'billing_city', 'billing_province', 'billing_country',
    'sdi_code', 'pec', 'iban', 'payment_terms', 'billing_notes'])]
class Company extends Model
{
    use Auditable, HasFactory, OwnedByUser, SoftDeletes;

    public const SEGMENTS = ['b2b', 'b2c', 'franchising'];

    public const TYPES = ['boutique', 'outlet', 'grossista', 'ecommerce', 'catena', 'altro'];

    public const STATUSES = ['lead', 'customer'];

    /** Campi di fatturazione (solo informativi per i lead, richiesti per i clienti). */
    public const BILLING_FIELDS = [
        'billing_name', 'billing_address', 'billing_zip', 'billing_city', 'billing_province', 'billing_country',
        'sdi_code', 'pec', 'iban', 'payment_terms', 'billing_notes',
    ];

    protected $attributes = ['segment' => 'b2b', 'status' => 'lead'];

    protected function casts(): array
    {
        return [
            'tax_code' => 'encrypted',
            'billing_address' => 'encrypted',
            'pec' => 'encrypted',
            'iban' => 'encrypted',
            'converted_at' => 'datetime',
            'address' => 'encrypted',
            'email' => 'encrypted',
            'phone' => 'encrypted',
            'notes' => 'encrypted',
        ];
    }

    protected function sdiCode(): Attribute
    {
        return Attribute::make(set: fn (?string $value) => $value ? strtoupper(trim($value)) : null);
    }

    public function isCustomer(): bool
    {
        return $this->status === 'customer';
    }

    /** Dati minimi per emettere fattura elettronica: intestazione, identificativo fiscale, indirizzo e SDI o PEC. */
    public function hasCompleteBilling(): bool
    {
        return filled($this->billing_name ?: $this->name)
            && (filled($this->vat_number) || filled($this->tax_code))
            && filled($this->billing_address) && filled($this->billing_zip) && filled($this->billing_city)
            && (filled($this->sdi_code) || filled($this->pec));
    }

    /** Il lead diventa cliente (chiamato quando si vince un'opportunità). */
    public function markAsCustomer(): void
    {
        if (! $this->isCustomer()) {
            $this->forceFill(['status' => 'customer', 'converted_at' => now()])->save();
        }
    }

    public function contacts(): HasMany
    {
        return $this->hasMany(Contact::class);
    }

    public function deals(): HasMany
    {
        return $this->hasMany(Deal::class);
    }

    public function activities(): HasMany
    {
        return $this->hasMany(Activity::class);
    }
}
