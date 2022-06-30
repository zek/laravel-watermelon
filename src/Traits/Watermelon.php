<?php

namespace NathanHeffley\LaravelWatermelon\Traits;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property array|null $watermelonAttributes
 * @mixin Model
 * @mixin SoftDeletes
 */
trait Watermelon
{
    public static function allowWatermelonCreate(): bool
    {
        return true;
    }

    public function scopeWatermelon($query)
    {
        return $query;
    }

    public function toWatermelonArray(): array
    {
        return $this->only($this->watermelonAttributes ?? null);
    }

    public function allowWatermelonUpdate(): bool
    {
        return true;
    }

    public function allowWatermelonDelete(): bool
    {
        return true;
    }
}
