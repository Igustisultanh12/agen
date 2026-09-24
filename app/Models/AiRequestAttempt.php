<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AiRequestAttempt extends Model
{
    use HasFactory;

    protected $fillable = [
        'usage_log_id',
        'internal_request_id',
        'provider_id',
        'model_id',
        'attempt_number',
        'status',
        'latency_ms',
        'error_message',
    ];

    protected function casts(): array
    {
        return [
            'attempt_number' => 'integer',
            'latency_ms' => 'integer',
        ];
    }

    public function usageLog(): BelongsTo
    {
        return $this->belongsTo(AiUsageLog::class, 'usage_log_id');
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
