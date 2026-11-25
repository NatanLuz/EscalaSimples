<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Holiday extends Model
{
    protected $fillable = ['name', 'date', 'type', 'scope', 'state', 'city', 'source', 'is_active'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    protected static function booted(): void
    {
        static::creating(fn (self $holiday) => $holiday->public_id ??= (string) Str::ulid());
    }
}
