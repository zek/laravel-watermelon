<?php

namespace NathanHeffley\LaravelWatermelon\Traits;

/**
 * @property array|null $watermelonAttributes
 */
trait Watermelon
{
    public function scopeWatermelon($query)
    {
        return $query;
    }

    public function toWatermelonArray(): array
    {
        return $this->only($this->watermelonAttributes ?? null);
    }

    public function allowWatermelonCreate(): bool
    {
        return true;
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
