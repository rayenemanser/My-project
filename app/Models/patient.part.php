<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Models\Review;
use App\Models\Payment;

class Patient extends Model
{
    protected $fillable = [
        'user_id', 'date_of_birth', 'blood_type', 'emergency_contact',
        'emergency_contact_name', 'address', 'wilaya', 'city', 'postal_code',
        'insurance_provider', 'insurance_number', 'allergies', 'chronic_conditions',
        'medications', 'primary_doctor_id', 'last_visit', 'next_appointment'
    ];

    protected $casts = [
        'date_of_birth' => 'date',
        'allergies' => 'array',
        'chronic_conditions' => 'array',
        'medications' => 'array',
        'last_visit' => 'date',
        'next_appointment' => 'date',
    ];

    protected $appends = ['age', 'full_address', 'initials'];

    // ========== العلاقات ==========
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function primaryDoctor(): BelongsTo
    {
        return $this->belongsTo(Doctor::class, 'primary_doctor_id');
    }

    public function appointments(): HasMany
    {
        return $this->hasMany(Appointment::class);
    }

    public function prescriptions(): HasMany
    {
        return $this->hasMany(Prescription::class);
    }

    public function medicalRecords(): HasMany
    {
        return $this->hasMany(MedicalRecord::class);
    }

    // public function allergiesList(): HasMany
    // {
    //     return $this->hasMany(Allergy::class);
    // }

    public function reviews(): HasMany
    {
        return $this->hasMany(Review::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    // ========== التوابع المساعدة ==========
    public function getAgeAttribute(): ?int
    {
        return $this->date_of_birth?->age;
    }

    public function getInitialsAttribute(): string
    {
        return $this->user->initials;
    }

    public function getFullAddressAttribute(): string
    {
        $parts = array_filter([
            $this->address,
            $this->city,
            $this->wilaya,
            $this->postal_code
        ]);
        return implode(', ', $parts);
    }

    public function getAvatarAttribute(): string
    {
        return $this->user->avatar_url;
    }

    public function getUpcomingAppointmentsAttribute()
    {
        return $this->appointments()
            ->where('appointment_date', '>=', now())
            ->whereIn('status', ['pending', 'confirmed', 'upcoming'])
            ->orderBy('appointment_date')
            ->orderBy('appointment_time')
            ->get();
    }

    public function getPastAppointmentsAttribute()
    {
        return $this->appointments()
            ->where('appointment_date', '<', now())
            ->orWhere('status', 'completed')
            ->orderBy('appointment_date', 'desc')
            ->get();
    }

    public function getActivePrescriptionsAttribute()
    {
        return $this->prescriptions()
            ->where('status', 'active')
            ->where(function($q) {
                $q->whereNull('expiry_date')
                  ->orWhere('expiry_date', '>=', now());
            })
            ->get();
    }

    // ========== النطاقات ==========
    public function scopeByWilaya($query, $wilaya)
    {
        return $query->where('wilaya', $wilaya);
    }

    public function scopeWithInsurance($query, $provider)
    {
        return $query->where('insurance_provider', $provider);
    }

    public function scopeHasAppointmentToday($query)
    {
        return $query->whereHas('appointments', function($q) {
            $q->whereDate('appointment_date', today());
        });
    }
}
