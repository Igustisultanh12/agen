<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AiUsageLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'project_id',
        'conversation_id',
        'message_id',
        'provider_id',
        'model_id',
        'agent_id',
        'internal_request_id',
        'provider_request_id',
        'input_tokens',
        'output_tokens',
        'cached_tokens',
        'reasoning_tokens',
        'total_tokens',
        'estimated_cost',
        'actual_cost',
        'currency',
        'duration_ms',
        'status',
        'usage_source',
        'error_message',
    ];

    protected function casts(): array
    {
        return [
            'input_tokens' => 'integer',
            'output_tokens' => 'integer',
            'cached_tokens' => 'integer',
            'reasoning_tokens' => 'integer',
            'total_tokens' => 'integer',
            'estimated_cost' => 'decimal:6',
            'actual_cost' => 'decimal:6',
            'duration_ms' => 'integer',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class);
    }

    public function message(): BelongsTo
    {
        return $this->belongsTo(Message::class);
    }

    public function provider(): BelongsTo
    {
        return $this->belongsTo(ModelProvider::class, 'provider_id');
    }

    public function model(): BelongsTo
    {
        return $this->belongsTo(AiModel::class, 'model_id');
    }

    public function agent(): BelongsTo
    {
        return $this->belongsTo(CodingAgent::class, 'agent_id');
    }

    public function attempts(): HasMany
    {
        return $this->hasMany(AiRequestAttempt::class, 'usage_log_id');
    }
}
