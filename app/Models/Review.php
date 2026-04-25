<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Review extends Model
{
    protected $fillable = [
        'patient_id',
        'doctor_id',
        'appointment_id',
        'rating',
        'comment',
        'is_anonymous',
    ];

    protected $casts = [
        'rating'       => 'integer',
        'is_anonymous' => 'boolean',
    ];

    // ─── Boot ─────────────────────────────────────────────────────────────────

    protected static function boot(): void
    {
        parent::boot();
        static::saved(fn($r)   => $r->doctor->doctorProfile?->updateRating());
        static::deleted(fn($r) => $r->doctor->doctorProfile?->updateRating());
    }

    // ─── Accessors ────────────────────────────────────────────────────────────

    public function getPatientNameAttribute(): string
    {
        return $this->is_anonymous ? 'Anonymous' : ($this->patient->name ?? 'Unknown');
    }

    // ─── Relationships ────────────────────────────────────────────────────────

    public function patient()
    {
        return $this->belongsTo(User::class, 'patient_id');
    }

    public function doctor()
    {
        return $this->belongsTo(User::class, 'doctor_id');
    }

    public function appointment()
    {
        return $this->belongsTo(Appointment::class);
    }
}