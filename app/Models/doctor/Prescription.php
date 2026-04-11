<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Prescription extends Model
{
    protected $fillable = [
        'patient_id', 'doctor_id', 'appointment_id',
        'medication', 'dosage', 'frequency', 'duration',
        'instructions', 'refills_remaining', 'refills_total',
        'status', 'prescribed_date', 'expiry_date', 'last_refill_date'
    ];

    protected $casts = [
        'prescribed_date' => 'date',
        'expiry_date' => 'date',
        'last_refill_date' => 'date',
        'refills_remaining' => 'integer',
        'refills_total' => 'integer',
    ];

    protected $appends = ['is_active', 'refills_left'];

    // ========== العلاقات ==========
    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }

    public function doctor(): BelongsTo
    {
        return $this->belongsTo(Doctor::class);
    }

    public function appointment(): BelongsTo
    {
        return $this->belongsTo(Appointment::class);
    }

    // ========== التوابع المساعدة ==========
    public function getIsActiveAttribute(): bool
    {
        return $this->status === 'active' && 
               ($this->expiry_date === null || $this->expiry_date->gte(now()));
    }

    public function getRefillsLeftAttribute(): int
    {
        return $this->refills_remaining;
    }

    public function canRefill(): bool
    {
        return $this->status === 'active' && 
               $this->refills_remaining > 0 &&
               ($this->expiry_date === null || $this->expiry_date->gte(now()));
    }

    public function processRefill(): void
    {
        if (!$this->canRefill()) {
            return;
        }

        $this->update([
            'refills_remaining' => $this->refills_remaining - 1,
            'last_refill_date' => now()
        ]);

        // إشعار للمريض
        Notification::create([
            'user_id' => $this->patient->user_id,
            'title' => 'Prescription Refilled',
            'message' => "Your prescription for {$this->medication} has been refilled. {$this->refills_remaining} refills remaining.",
            'type' => 'prescription',
            'data' => ['prescription_id' => $this->id]
        ]);
    }

    // ========== النطاقات ==========
    public function scopeActive($query)
    {
        return $query->where('status', 'active')
                     ->where(function($q) {
                         $q->whereNull('expiry_date')
                           ->orWhere('expiry_date', '>=', now());
                     });
    }

    public function scopeByPatient($query, $patientId)
    {
        return $query->where('patient_id', $patientId);
    }

    public function scopeByDoctor($query, $doctorId)
    {
        return $query->where('doctor_id', $doctorId);
    }

    public function scopeNeedsRefill($query)
    {
        return $query->where('status', 'active')
                     ->where('refills_remaining', '>', 0);
    }
}