<?php

namespace NathanHeffley\LaravelWatermelon\Traits;

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
}
