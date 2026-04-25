<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Support\Str;

class Prescription extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'patient_id',
        'doctor_id',
        'appointment_id',
        'prescription_number',
        'medication',
        'dosage',
        'frequency',
        'duration',
        'instructions',
        'refills_remaining',
        'refills_total',
        'status',              // active|completed|cancelled|expired
        'prescribed_date',
        'expiry_date',
        'last_refill_date',
    ];

    protected $casts = [
        'prescribed_date'   => 'date',
        'expiry_date'       => 'date',
        'last_refill_date'  => 'date',
        'refills_remaining' => 'integer',
        'refills_total'     => 'integer',
    ];

    // ─── Boot ─────────────────────────────────────────────────────────────────

    protected static function boot(): void
    {
        parent::boot();
        static::creating(function ($p) {
            $p->prescription_number ??= 'RX-' . strtoupper(Str::random(8)) . '-' . now()->year;
        });
    }

    // ─── Scopes ───────────────────────────────────────────────────────────────

    public function scopeActive($q)
    {
        return $q->where('status', 'active')
                 ->where(function ($q) {
                     $q->whereNull('expiry_date')
                       ->orWhere('expiry_date', '>=', today());
                 });
    }

    public function scopeByDoctor($q, $doctorId)   { return $q->where('doctor_id', $doctorId); }
    public function scopeByPatient($q, $patientId) { return $q->where('patient_id', $patientId); }

    // ─── Accessors ────────────────────────────────────────────────────────────

    public function getIsActiveAttribute(): bool
    {
        return $this->status === 'active'
            && ($this->expiry_date === null || $this->expiry_date->gte(today()));
    }

    public function getIsExpiredAttribute(): bool
    {
        return $this->expiry_date && $this->expiry_date->lt(today());
    }

    public function canRefill(): bool
    {
        return $this->is_active && $this->refills_remaining > 0;
    }

    // ─── Methods ──────────────────────────────────────────────────────────────

    public function processRefill(): bool
    {
        if (!$this->canRefill()) return false;

        $this->update([
            'refills_remaining' => $this->refills_remaining - 1,
            'last_refill_date'  => today(),
        ]);

        AppNotification::send(
            $this->patient_id,
            'prescription_refilled',
            'Prescription Refilled',
            "Your prescription for {$this->medication} has been refilled. {$this->refills_remaining} refills remaining.",
            ['prescription_id' => $this->id]
        );

        return true;
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