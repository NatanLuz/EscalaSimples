<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Department extends Model
{
    protected $fillable = ['name', 'is_active'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    protected static function booted(): void
    {
        static::creating(function (Department $department): void {
            $department->public_id ??= (string) Str::ulid();
        });
        static::saving(function (Department $department): void {
            $department->name = self::normalizeName($department->name);
            $department->name_key = self::nameKey($department->name);
        });
    }

    public static function normalizeName(string $name): string
    {
        return trim(preg_replace('/\s+/u', ' ', $name));
    }

    public static function nameKey(string $name): string
    {
        return hash('sha256', mb_strtolower(self::normalizeName($name), 'UTF-8'));
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function employees(): HasMany
    {
        return $this->hasMany(Employee::class);
    }
}
