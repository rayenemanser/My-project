<?php

namespace App\Traits;

trait HasInitials
{
    public function getInitialsAttribute(): string
    {
        $words = explode(' ', $this->name ?? '');
        return strtoupper(
            collect($words)
                ->filter()
                ->map(fn($w) => substr($w, 0, 1))
                ->take(2)
                ->implode('')
        );
    }
}