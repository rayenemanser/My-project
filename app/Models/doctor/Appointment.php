<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Appointment extends Model
{
    protected $table = 'appointments';

    protected $fillable = [
        'doctor_id',
        'patient_id',
        'appointment_datetime',
        'type',              // 'In-person', 'Video'
        'status',            // 'pending', 'confirmed', 'upcoming', 'completed', 'cancelled', 'no_show'
        'duration_minutes',
        'reason',
        'symptoms',
        'diagnosis',
        'notes',
        'location',
        'consultation_fee',
        'insurance_coverage',
        'payment_status',
        'payment_method',
        'reminder_sent',
        'reminder_datetime',
        'cancelled_at',
        'cancellation_reason',
        'completed_at'
    ];

    protected $casts = [
        'appointment_datetime' => 'datetime',
        'reminder_datetime' => 'datetime',
        'cancelled_at' => 'datetime',
        'completed_at' => 'datetime',
        'reminder_sent' => 'boolean',
        'duration_minutes' => 'integer',
        'consultation_fee' => 'decimal:2',
        'insurance_coverage' => 'integer',
    ];

    protected $appends = [
        'formatted_time',
        'formatted_date',
        'is_today',
        'is_upcoming'
    ];

    /**
     * ========== العلاقات ==========
     */
    public function doctor(): BelongsTo
    {
        return $this->belongsTo(Doctor::class);
    }

    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }

    public function prescription(): HasOne
    {
        return $this->hasOne(Prescription::class);
    }

    public function medicalRecord(): HasOne
    {
        return $this->hasOne(MedicalRecord::class);
    }

    
    /**
     * ========== النطاقات (Scopes) ==========
     */
    public function scopeToday($query)
    {
        return $query->whereDate('appointment_datetime', today());
    }

    public function scopeTomorrow($query)
    {
        return $query->whereDate('appointment_datetime', today()->addDay());
    }

    public function scopeUpcoming($query)
    {
        return $query->where('appointment_datetime', '>', now())
            ->whereIn('status', ['pending', 'confirmed', 'upcoming']);
    }

    public function scopePast($query)
    {
        return $query->where('appointment_datetime', '<', now())
            ->orWhere('status', 'completed');
    }

    public function scopeByStatus($query, $status)
    {
        return $query->where('status', $status);
    }

    public function scopeByDoctor($query, $doctorId)
    {
        return $query->where('doctor_id', $doctorId);
    }

    public function scopeByPatient($query, $patientId)
    {
        return $query->where('patient_id', $patientId);
    }

    public function scopeByDateRange($query, $start, $end)
    {
        return $query->whereBetween('appointment_datetime', [$start, $end]);
    }

    public function scopeNeedsReminder($query)
    {
        return $query->whereDate('appointment_datetime', today()->addDay())
            ->where('reminder_sent', false)
            ->whereIn('status', ['confirmed', 'upcoming']);
    }

    /**
     * ========== التوابع المساعدة (Accessors) ==========
     */
    public function getFormattedTimeAttribute(): string
    {
        return $this->appointment_datetime->format('H:i');
    }

    public function getFormattedDateAttribute(): string
    {
        return $this->appointment_datetime->format('Y-m-d');
    }

    public function getIsTodayAttribute(): bool
    {
        return $this->appointment_datetime->isToday();
    }

    public function getIsUpcomingAttribute(): bool
    {
        return $this->appointment_datetime->isFuture() &&
               in_array($this->status, ['pending', 'confirmed', 'upcoming']);
    }

    public function getDoctorDetailsAttribute(): array
    {
        return [
            'id' => $this->doctor->id,
            'name' => $this->doctor->full_name,
            'specialty' => $this->doctor->specialty,
            'avatar' => $this->doctor->avatar,
            'rating' => $this->doctor->rating,
        ];
    }

    public function getPatientDetailsAttribute(): array
    {
        return [
            'id' => $this->patient->id,
            'name' => $this->patient->user->name,
            'initials' => $this->patient->initials,
            'avatar' => $this->patient->avatar,
            'age' => $this->patient->age,
        ];
    }

    /**
     * ========== التوابع المساعدة (Methods) ==========
     */
    public function canBeCancelled(): bool
    {
        return in_array($this->status, ['pending', 'confirmed']) &&
               $this->appointment_datetime->gt(now()->addHours(24));
    }

    public function canBeRescheduled(): bool
    {
        return in_array($this->status, ['pending', 'confirmed']) &&
               $this->appointment_datetime->gt(now()->addHours(2));
    }

    public function markAsCompleted(): void
    {
        $this->update([
            'status' => 'completed',
            'completed_at' => now()
        ]);

        // تحديث آخر زيارة للمريض
        $this->patient->update(['last_visit' => now()]);

        // إنشاء إشعار للمريض
        Notification::create([
            'user_id' => $this->patient->user_id,
            'title' => 'Appointment Completed',
            'message' => "Your appointment with Dr. {$this->doctor->full_name} has been completed.",
            'type' => 'appointment_reminder',
            'data' => ['appointment_id' => $this->id]
        ]);
    }

    public function markAsNoShow(): void
    {
        $this->update(['status' => 'no_show']);

        // تحرير الوقت slot
        TimeSlot::where('doctor_id', $this->doctor_id)
            ->whereDate('slot_date', $this->appointment_datetime)
            ->whereTime('start_time', $this->formatted_time)
            ->update(['status' => 'available']);
    }

    public function sendReminder(): void
    {
        if ($this->reminder_sent) {
            return;
        }

        Notification::create([
            'user_id' => $this->patient->user_id,
            'title' => 'Appointment Reminder',
            'message' => "You have an appointment with Dr. {$this->doctor->full_name} tomorrow at {$this->formatted_time}.",
            'type' => 'appointment_reminder',
            'data' => ['appointment_id' => $this->id],
            'action_url' => "/appointments/{$this->id}",
            'action_text' => 'View Details'
        ]);

        $this->update([
            'reminder_sent' => true,
            'reminder_datetime' => now()
        ]);
    }
}
