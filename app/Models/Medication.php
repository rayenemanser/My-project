<?php
// app/Models/Medication.php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Medication extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'pharmacist_id',
        'medication_name',
        'category',
        'stock_quantity',
        'reorder_level',
        'expiry_date',
        'price',
        'description',
        'requires_prescription',
    ];

    protected $casts = [
        'expiry_date'            => 'date',
        'requires_prescription'  => 'boolean',
        'price'                  => 'decimal:2',
    ];

    // ─── Scopes ───────────────────────────────────────────────────────────────

    public function scopeLowStock($q)
    {
        return $q->whereColumn('stock_quantity', '<=', 'reorder_level');
    }

    public function scopeExpiringSoon($q, int $days = 90)
    {
        return $q->whereBetween('expiry_date', [today(), today()->addDays($days)]);
    }

    public function scopeForPharmacist($q, int $pharmacistId)
    {
        return $q->where('pharmacist_id', $pharmacistId);
    }

    // ─── Accessors ────────────────────────────────────────────────────────────

    public function getIsLowStockAttribute(): bool
    {
        return $this->stock_quantity <= $this->reorder_level;
    }

    public function getIsExpiredAttribute(): bool
    {
        return $this->expiry_date->isPast();
    }

    public function getDaysUntilExpiryAttribute(): int
    {
        return today()->diffInDays($this->expiry_date, false);
    }

    // ─── Relationships ────────────────────────────────────────────────────────

    public function pharmacist()
    {
        return $this->belongsTo(User::class, 'pharmacist_id');
    }
}
