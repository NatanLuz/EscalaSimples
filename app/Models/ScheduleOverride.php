<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class ScheduleOverride extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['is_work' => 'boolean'];
    }

    protected static function booted(): void
    {
        static::creating(fn (self $override) => $override->public_id ??= (string) Str::ulid());
    }
}
