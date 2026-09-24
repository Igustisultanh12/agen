<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class UserGroup extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'slug',
        'description',
        'monthly_token_limit',
        'daily_token_limit',
        'weekly_token_limit',
        'request_limit_per_minute',
        'concurrent_session_limit',
        'max_projects',
        'max_storage_bytes',
        'max_file_size_bytes',
        'is_default',
    ];

    protected function casts(): array
    {
        return [
            'monthly_token_limit' => 'integer',
            'daily_token_limit' => 'integer',
            'weekly_token_limit' => 'integer',
            'request_limit_per_minute' => 'integer',
            'concurrent_session_limit' => 'integer',
            'max_projects' => 'integer',
            'max_storage_bytes' => 'integer',
            'max_file_size_bytes' => 'integer',
            'is_default' => 'boolean',
        ];
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function allowedModels(): BelongsToMany
    {
        return $this->belongsToMany(AiModel::class, 'user_group_model', 'user_group_id', 'model_id');
    }

    public function allowedAgents(): BelongsToMany
    {
        return $this->belongsToMany(CodingAgent::class, 'user_group_agent', 'user_group_id', 'agent_id');
    }
}
