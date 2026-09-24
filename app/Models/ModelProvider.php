<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Crypt;

class ModelProvider extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'slug',
        'type',
        'base_url',
        'api_key_encrypted',
        'status',
        'priority',
        'health_status',
        'latency_ms',
        'failure_rate',
        'last_error',
        'last_checked_at',
        'settings',
    ];

    protected $hidden = [
        'api_key_encrypted',
    ];

    protected function casts(): array
    {
        return [
            'priority' => 'integer',
            'latency_ms' => 'integer',
            'failure_rate' => 'decimal:2',
            'last_checked_at' => 'datetime',
            'settings' => 'array',
        ];
    }

    public function setApiKeyAttribute(?string $value): void
    {
        $this->attributes['api_key_encrypted'] = $value ? Crypt::encryptString($value) : null;
    }

    public function getApiKeyDecryptedAttribute(): ?string
    {
        if (empty($this->attributes['api_key_encrypted'])) {
            return null;
        }

        try {
            return Crypt::decryptString($this->attributes['api_key_encrypted']);
        } catch (\Exception) {
            return null;
        }
    }

    public function models(): HasMany
    {
        return $this->hasMany(AiModel::class, 'provider_id');
    }

    public function pricings(): HasMany
    {
        return $this->hasMany(ModelPricing::class, 'provider_id');
    }

    public function usageLogs(): HasMany
    {
        return $this->hasMany(AiUsageLog::class, 'provider_id');
    }
}
