<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class ApiKey extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'name',
        'key_hash',
        'key_preview',
        'permissions',
        'last_used_at',
        'expires_at',
        'is_active',
    ];

    protected $hidden = [
        'key_hash',
    ];

    protected function casts(): array
    {
        return [
            'permissions' => 'array',
            'last_used_at' => 'datetime',
            'expires_at' => 'datetime',
            'is_active' => 'boolean',
        ];
    }

    public static function generate(User $user, string $name, array $permissions = [], ?\DateTimeInterface $expiresAt = null): array
    {
        $rawKey = 'fcc_' . Str::random(40);
        $keyHash = hash('sha256', $rawKey);
        $preview = substr($rawKey, 0, 8) . '...' . substr($rawKey, -4);

        $apiKey = static::create([
            'user_id' => $user->id,
            'name' => $name,
            'key_hash' => $keyHash,
            'key_preview' => $preview,
            'permissions' => empty($permissions) ? ['chat', 'models.read', 'projects.read', 'projects.write', 'files.read', 'files.write', 'usage.read'] : $permissions,
            'expires_at' => $expiresAt,
            'is_active' => true,
        ]);

        return [
            'api_key' => $apiKey,
            'plain_text_key' => $rawKey,
        ];
    }

    public function hasPermission(string $permission): bool
    {
        if (empty($this->permissions)) {
            return false;
        }

        return in_array('*', $this->permissions) || in_array($permission, $this->permissions);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
