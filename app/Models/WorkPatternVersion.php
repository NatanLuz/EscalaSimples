<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WorkPatternVersion extends Model
{
    protected $guarded = ['id'];

    protected static function booted(): void
    {
        static::updating(fn () => throw new \LogicException('Versões de padrões não podem ser alteradas.'));
        static::deleting(fn () => throw new \LogicException('Versões de padrões não podem ser excluídas.'));
    }

    protected function casts(): array
    {
        return ['sequence' => 'array'];
    }

    public function pattern(): BelongsTo
    {
        return $this->belongsTo(WorkPattern::class, 'work_pattern_id');
    }
}
