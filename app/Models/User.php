<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, Notifiable;

    protected $fillable = [
        'name',
        'email',
        'password',
        'role',
        'status',
        'user_group_id',
        'avatar_url',
        'preferences',
        'custom_instructions',
        'google_id',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'preferences' => 'array',
        ];
    }

    public function isAdmin(): bool
    {
        return $this->role === 'admin';
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    public function isSuspended(): bool
    {
        return $this->status === 'suspended';
    }

    public function userGroup(): BelongsTo
    {
        return $this->belongsTo(UserGroup::class, 'user_group_id');
    }

    public function quota(): HasOne
    {
        return $this->hasOne(UserQuota::class);
    }

    public function projects(): HasMany
    {
        return $this->hasMany(Project::class);
    }

    public function conversations(): HasMany
    {
        return $this->hasMany(Conversation::class);
    }

    public function usageLogs(): HasMany
    {
        return $this->hasMany(AiUsageLog::class);
    }

    public function apiKeys(): HasMany
    {
        return $this->hasMany(ApiKey::class);
    }

    public function auditLogs(): HasMany
    {
        return $this->hasMany(AuditLog::class);
    }

    public function userNotifications(): HasMany
    {
        return $this->hasMany(Notification::class);
    }

    public function canAccessModel(AiModel $model): bool
    {
        if ($this->isAdmin()) {
            return true;
        }

        if ($model->status !== 'active') {
            return false;
        }

        if ($model->visibility === 'admin') {
            return false;
        }

        if ($model->visibility === 'all') {
            return true;
        }

        if ($this->userGroup) {
            return $this->userGroup->allowedModels()->where('ai_models.id', $model->id)->exists();
        }

        return false;
    }

    public function canAccessAgent(CodingAgent $agent): bool
    {
        if ($this->isAdmin()) {
            return true;
        }

        if (!$agent->is_active) {
            return false;
        }

        if ($this->userGroup) {
            return $this->userGroup->allowedAgents()->where('coding_agents.id', $agent->id)->exists();
        }

        return true;
    }
}
