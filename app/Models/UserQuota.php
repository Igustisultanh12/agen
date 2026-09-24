<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserQuota extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'monthly_token_limit',
        'daily_token_limit',
        'weekly_token_limit',
        'used_input_tokens',
        'used_output_tokens',
        'used_total_tokens',
        'used_cost',
        'daily_reset_at',
        'monthly_reset_at',
    ];

    protected function casts(): array
    {
        return [
            'monthly_token_limit' => 'integer',
            'daily_token_limit' => 'integer',
            'weekly_token_limit' => 'integer',
            'used_input_tokens' => 'integer',
            'used_output_tokens' => 'integer',
            'used_total_tokens' => 'integer',
            'used_cost' => 'decimal:6',
            'daily_reset_at' => 'datetime',
            'monthly_reset_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function remainingMonthlyTokens(): int
    {
        return max(0, $this->monthly_token_limit - $this->used_total_tokens);
    }

    public function monthlyUsagePercentage(): float
    {
        if ($this->monthly_token_limit <= 0) {
            return 100.0;
        }

        return round(($this->used_total_tokens / $this->monthly_token_limit) * 100, 2);
    }
}
