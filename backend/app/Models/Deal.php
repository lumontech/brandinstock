<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\OwnedByUser;
use App\Support\LeadSource;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable([
    'title', 'company_id', 'contact_id', 'pipeline_stage_id', 'value', 'currency', 'brand',
    'product_category', 'quantity', 'expected_close_date', 'source', 'lost_reason', 'notes',
])]
class Deal extends Model
{
    use Auditable, HasFactory, OwnedByUser, SoftDeletes;

    public const SOURCES = LeadSource::ALL;

    protected function casts(): array
    {
        return [
            'value' => 'decimal:2',
            'quantity' => 'integer',
            'expected_close_date' => 'date',
            'closed_at' => 'datetime',
            'notes' => 'encrypted',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (Deal $deal) {
            if ($deal->isDirty('pipeline_stage_id')) {
                $stage = PipelineStage::find($deal->pipeline_stage_id);
                $deal->closed_at = $stage?->isClosed() ? ($deal->closed_at ?? now()) : null;
            }
        });

        // Opportunità vinta: il lead diventa cliente.
        static::saved(function (Deal $deal) {
            if ($deal->wasChanged('pipeline_stage_id') || $deal->wasRecentlyCreated) {
                if (PipelineStage::whereKey($deal->pipeline_stage_id)->value('is_won')) {
                    $deal->company?->markAsCustomer();
                }
            }
        });
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class);
    }

    public function stage(): BelongsTo
    {
        return $this->belongsTo(PipelineStage::class, 'pipeline_stage_id');
    }

    public function activities(): HasMany
    {
        return $this->hasMany(Activity::class);
    }
}
