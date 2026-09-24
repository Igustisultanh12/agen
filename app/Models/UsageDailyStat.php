<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UsageDailyStat extends Model
{
    use HasFactory;

    protected $fillable = [
        'date',
        'user_id',
        'provider_id',
        'model_id',
        'requests',
        'input_tokens',
        'output_tokens',
        'total_tokens',
        'estimated_cost',
    ];

    protected function casts(): array
    {
        return [
            'date' => 'date',
            'requests' => 'integer',
            'input_tokens' => 'integer',
            'output_tokens' => 'integer',
            'total_tokens' => 'integer',
            'estimated_cost' => 'decimal:6',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
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
