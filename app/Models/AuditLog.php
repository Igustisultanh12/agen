<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Http\Request;

class AuditLog extends Model
{
    use HasFactory;

    public $timestamps = false;

    protected $fillable = [
        'user_id',
        'action',
        'resource_type',
        'resource_id',
        'ip_address',
        'user_agent',
        'metadata',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'metadata' => 'array',
            'created_at' => 'datetime',
        ];
    }

    public static function log(
        string $action,
        ?string $resourceType = null,
        ?string $resourceId = null,
        ?array $metadata = null,
        ?User $user = null,
        ?Request $request = null
    ): self {
        $currentUser = $user ?? auth()->user();
        $req = $request ?? request();

        return static::create([
            'user_id' => $currentUser?->id,
            'action' => $action,
            'resource_type' => $resourceType,
            'resource_id' => $resourceId ? (string) $resourceId : null,
            'ip_address' => $req?->ip(),
            'user_agent' => $req?->userAgent(),
            'metadata' => $metadata,
            'created_at' => now(),
        ]);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
