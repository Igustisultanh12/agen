<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ModelPricing extends Model
{
    use HasFactory;

    protected $fillable = [
        'provider_id',
        'model_id',
        'input_price_per_1m',
        'output_price_per_1m',
        'cached_input_price_per_1m',
        'reasoning_price_per_1m',
        'currency',
        'effective_from',
        'effective_until',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'input_price_per_1m' => 'decimal:6',
            'output_price_per_1m' => 'decimal:6',
            'cached_input_price_per_1m' => 'decimal:6',
            'reasoning_price_per_1m' => 'decimal:6',
            'effective_from' => 'date',
            'effective_until' => 'date',
            'is_active' => 'boolean',
        ];
    }

    public function provider(): BelongsTo
    {
        return $this->belongsTo(ModelProvider::class, 'provider_id');
    }

    public function model(): BelongsTo
    {
        return $this->belongsTo(AiModel::class, 'model_id');
    }
}
