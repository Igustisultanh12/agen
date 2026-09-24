<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProjectFile extends Model
{
    use HasFactory;

    protected $fillable = [
        'project_id',
        'parent_id',
        'path',
        'filename',
        'extension',
        'mime_type',
        'size_bytes',
        'storage_path',
        'is_directory',
    ];

    protected function casts(): array
    {
        return [
            'size_bytes' => 'integer',
            'is_directory' => 'boolean',
        ];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(ProjectFile::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(ProjectFile::class, 'parent_id');
    }
}
