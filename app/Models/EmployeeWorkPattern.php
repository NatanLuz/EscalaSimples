<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class EmployeeWorkPattern extends Model
{
    protected $guarded = ['id'];

    protected static function booted(): void
    {
        static::creating(fn (self $assignment) => $assignment->public_id ??= (string) Str::ulid());
    }

    public function version(): BelongsTo
    {
        return $this->belongsTo(WorkPatternVersion::class, 'work_pattern_version_id');
    }
}
