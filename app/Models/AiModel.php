<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class AiModel extends Model
{
    use HasFactory;

    protected $fillable = [
        'provider_id',
        'name',
        'slug',
        'provider_model_id',
        'description',
        'context_window',
        'max_tokens',
        'category',
        'status',
        'visibility',
        'is_default',
        'supports_streaming',
        'supports_tools',
    ];

    protected function casts(): array
    {
        return [
            'context_window' => 'integer',
            'max_tokens' => 'integer',
            'is_default' => 'boolean',
            'supports_streaming' => 'boolean',
            'supports_tools' => 'boolean',
        ];
    }

    public function provider(): BelongsTo
    {
        return $this->belongsTo(ModelProvider::class, 'provider_id');
    }

    public function pricings(): HasMany
    {
        return $this->hasMany(ModelPricing::class, 'model_id');
    }

    public function activePricing(): HasOne
    {
        return $this->hasOne(ModelPricing::class, 'model_id')->where('is_active', true)->latestOfMany();
    }

    public function userGroups(): BelongsToMany
    {
        return $this->belongsToMany(UserGroup::class, 'user_group_model', 'model_id', 'user_group_id');
    }

    public function fallbackModels(): HasMany
    {
        return $this->hasMany(FallbackModel::class, 'primary_model_id')->orderBy('priority');
    }

    public function usageLogs(): HasMany
    {
        return $this->hasMany(AiUsageLog::class, 'model_id');
    }
}
