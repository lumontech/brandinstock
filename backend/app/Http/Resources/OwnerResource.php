<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** Versione ridotta dell'utente, da usare nelle relazioni (niente email né dati di sicurezza). */
class OwnerResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return ['id' => $this->id, 'name' => $this->name];
    }
}
