<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Appointment extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'patient_id',
        'doctor_id',
        'appointment_date',
        'appointment_time',
        'duration',
        'status',              // pending|confirmed|cancelled|completed|no_show
        'type',                // in_person|online
        'reason',
        'notes',
        'cancellation_reason',
        'cancelled_by',        // patient|doctor
        'reminder_sent',
        'reminder_datetime',
        'completed_at',
        'confirmed_at',
        'cancelled_at',
    ];

    protected $casts = [
        'appointment_date'  => 'date',
        'completed_at'      => 'datetime',
        'confirmed_at'      => 'datetime',
        'cancelled_at'      => 'datetime',
        'reminder_datetime' => 'datetime',
        'reminder_sent'     => 'boolean',
        'duration'          => 'integer',
    ];

    // ─── Scopes ───────────────────────────────────────────────────────────────

    public function scopeToday($q)
    {
        return $q->whereDate('appointment_date', today());
    }

    public function scopeUpcoming($q)
    {
        return $q->where('appointment_date', '>=', today())
                 ->whereIn('status', ['pending', 'confirmed'])
                 ->orderBy('appointment_date')
                 ->orderBy('appointment_time');
    }

    public function scopePending($q)   { return $q->where('status', 'pending'); }
    public function scopeConfirmed($q) { return $q->where('status', 'confirmed'); }
    public function scopeCompleted($q) { return $q->where('status', 'completed'); }

    public function scopeForDoctor($q, int $doctorId)
    {
        return $q->where('doctor_id', $doctorId);
    }

    public function scopeForPatient($q, int $patientId)
    {
        return $q->where('patient_id', $patientId);
    }

    // ─── Accessors ────────────────────────────────────────────────────────────

    public function getCanBeCancelledAttribute(): bool
    {
        return in_array($this->status, ['pending', 'confirmed'])
            && $this->appointment_date->isFuture();
    }

    public function getCanBeRescheduledAttribute(): bool
    {
        return in_array($this->status, ['pending', 'confirmed'])
            && $this->appointment_date->gt(now()->addHours(2));
    }

    public function getIsReviewableAttribute(): bool
    {
        return $this->status === 'completed' && !$this->review()->exists();
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

    public function prescription()
    {
        return $this->hasOne(Prescription::class);
    }

    public function medicalRecord()
    {
        return $this->hasOne(MedicalRecord::class);
    }

    public function review()
    {
        return $this->hasOne(Review::class);
    }

    // ─── Methods ──────────────────────────────────────────────────────────────

    public function markAsCompleted(): void
    {
        $this->update([
            'status'       => 'completed',
            'completed_at' => now(),
        ]);

        $this->patient->patientProfile?->update(['last_visit' => today()]);

        AppNotification::send(
            $this->patient_id,
            'appointment_completed',
            'Appointment Completed',
            "Your appointment with Dr. {$this->doctor->name} has been completed.",
            ['appointment_id' => $this->id]
        );
    }

    public function sendReminder(): void
    {
        if ($this->reminder_sent) return;

        AppNotification::send(
            $this->patient_id,
            'appointment_reminder',
            'Appointment Reminder',
            "You have an appointment with Dr. {$this->doctor->name} on {$this->appointment_date->format('d/m/Y')} at {$this->appointment_time}.",
            ['appointment_id' => $this->id]
        );

        $this->update(['reminder_sent' => true, 'reminder_datetime' => now()]);
    }
}