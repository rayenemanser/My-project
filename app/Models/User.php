<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable, SoftDeletes;

    protected $fillable = [
        'name', 'email', 'password', 'role',
        'phone', 'avatar', 'gender',
        'birth_date', 'address', 'city', 'is_active',
    ];

    protected $hidden = ['password', 'remember_token'];

    protected $casts = [
        'birth_date'        => 'date',
        'email_verified_at' => 'datetime',
        'is_active'         => 'boolean',
        'password'          => 'hashed',
    ];

    // ─── Role helpers ──────────────────────────────────────────────────────────

    public function isDoctor(): bool     { return $this->role === 'doctor'; }
    public function isPatient(): bool    { return $this->role === 'patient'; }
    public function isPharmacist(): bool { return $this->role === 'pharmacist'; }

    public function getAgeAttribute(): ?int
    {
        return $this->birth_date?->age;
    }

    // ─── Relationships ─────────────────────────────────────────────────────────

    public function doctorProfile()
    {
        return $this->hasOne(DoctorProfile::class);
    }

    public function patientProfile()
    {
        return $this->hasOne(PatientProfile::class);
    }

    // Appointments as patient
    public function patientAppointments()
    {
        return $this->hasMany(Appointment::class, 'patient_id');
    }

    // Appointments as doctor
    public function doctorAppointments()
    {
        return $this->hasMany(Appointment::class, 'doctor_id');
    }

    public function availabilities()
    {
        return $this->hasMany(DoctorAvailability::class, 'doctor_id');
    }

    public function prescriptionsAsDoctor()
    {
        return $this->hasMany(Prescription::class, 'doctor_id');
    }

    public function prescriptionsAsPatient()
    {
        return $this->hasMany(Prescription::class, 'patient_id');
    }

    public function medicalRecordsAsPatient()
    {
        return $this->hasMany(MedicalRecord::class, 'patient_id');
    }

    public function medicalRecordsAsDoctor()
    {
        return $this->hasMany(MedicalRecord::class, 'doctor_id');
    }

    public function notifications()
    {
        return $this->hasMany(AppNotification::class);
    }

    public function reviewsAsPatient()
    {
        return $this->hasMany(Review::class, 'patient_id');
    }

    public function reviewsAsDoctor()
    {
        return $this->hasMany(Review::class, 'doctor_id');
    }

    public function articles()
    {
        return $this->hasMany(Article::class, 'doctor_id');
    }
}