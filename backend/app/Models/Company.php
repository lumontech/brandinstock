<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\OwnedByUser;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['name', 'segment', 'vat_number', 'tax_code', 'type', 'city', 'province', 'country', 'address', 'email', 'phone', 'website', 'notes'])]
class Company extends Model
{
    use Auditable, HasFactory, OwnedByUser, SoftDeletes;

    public const SEGMENTS = ['b2b', 'b2c', 'franchising'];

    public const TYPES = ['boutique', 'outlet', 'grossista', 'ecommerce', 'catena', 'altro'];

    protected $attributes = ['segment' => 'b2b'];

    protected function casts(): array
    {
        return [
            'tax_code' => 'encrypted',
            'address' => 'encrypted',
            'email' => 'encrypted',
            'phone' => 'encrypted',
            'notes' => 'encrypted',
        ];
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
