<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class WorkPattern extends Model
{
    protected $fillable = ['name', 'is_active'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    protected static function booted(): void
    {
        static::creating(fn (self $pattern) => $pattern->public_id ??= (string) Str::ulid());
    }

    public function versions(): HasMany
    {
        return $this->hasMany(WorkPatternVersion::class);
    }
}
