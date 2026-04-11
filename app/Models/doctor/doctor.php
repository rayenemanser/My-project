<?php
// app/Models/Doctor.php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class Doctor extends Authenticatable
{
    use HasApiTokens, Notifiable;

    protected $table = 'doctors';

    protected $fillable = [
        'first_name',
        'last_name',
        'email',
        'password',
        'specialty',
        'phone',
        'license_number',
        'bio',
        'profile_photo',
        'working_hours',
        'consultation_fee',
        'is_available',
        'initials',
        'timezone',
        'address',
        'city',
        'country',
        'languages',
        'education',
        'experience',
        'awards'
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $casts = [
        'email_verified_at' => 'datetime',
        'working_hours' => 'array',
        'languages' => 'array',
        'education' => 'array',
        'experience' => 'array',
        'awards' => 'array',
        'is_available' => 'boolean',
        'consultation_fee' => 'decimal:2',
    ];

    /**
     * Relations
     */
    public function patients()
    {
        return $this->hasMany(Patient::class);
    }

    public function appointments()
    {
        return $this->hasMany(Appointment::class);
    }

    public function prescriptions()
    {
        return $this->hasMany(Prescription::class);
    }

    public function medicalRecords()
    {
        return $this->hasMany(MedicalRecord::class);
    }

    public function schedules()
    {
        return $this->hasMany(DoctorSchedule::class);
    }

    /**
     * Accessors
     */
    public function getFullNameAttribute()
    {
        return "Dr. {$this->first_name} {$this->last_name}";
    }

    public function getInitialsAttribute()
    {
        return strtoupper(substr($this->first_name, 0, 1) . substr($this->last_name, 0, 1));
    }

    /**
     * Scopes
     */
    public function scopeAvailable($query)
    {
        return $query->where('is_available', true);
    }

    public function scopeBySpecialty($query, $specialty)
    {
        return $query->where('specialty', $specialty);
    }

    /**
     * Methods
     */
    public function isAvailableAt($datetime)
    {
        $dayOfWeek = strtolower($datetime->format('l'));
        $time = $datetime->format('H:i');

        if (!isset($this->working_hours[$dayOfWeek])) {
            return false;
        }

        $workingDay = $this->working_hours[$dayOfWeek];
        return $time >= $workingDay['start'] && $time <= $workingDay['end'];
    }
}
