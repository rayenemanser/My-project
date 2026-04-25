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
        'name',
        'email',
        'password',
        'role',           // DOCTOR | PATIENT
        'status',         // active | inactive | suspended
        'phone',
        'avatar',
        'gender',
        'date_of_birth',
        'address',
        'city',
        'country',
        'is_online',
        'last_login_at',
    ];

    protected $hidden = ['password', 'remember_token'];

    protected $casts = [
        'date_of_birth'     => 'date',
        'email_verified_at' => 'datetime',
        'last_login_at'     => 'datetime',
        'is_online'         => 'boolean',
        'password'          => 'hashed',
    ];

    // ─── Role & Status helpers ────────────────────────────────────────────────

    public function isDoctor(): bool     { return $this->role === 'DOCTOR'; }
    public function isPatient(): bool    { return $this->role === 'PATIENT'; }
    public function isPharmacist(): bool { return $this->role === 'PHARMACIST'; }
    public function isActive(): bool     { return $this->status === 'active'; }

    // ─── Accessors ────────────────────────────────────────────────────────────

    public function getAgeAttribute(): ?int
    {
        return $this->date_of_birth?->age;
    }

    public function getInitialsAttribute(): string
    {
        $words = explode(' ', $this->name);
        return strtoupper(
            collect($words)->map(fn($w) => substr($w, 0, 1))->take(2)->implode('')
        );
    }

    public function getAvatarUrlAttribute(): string
    {
        return $this->avatar
            ? asset('storage/' . $this->avatar)
            : 'https://ui-avatars.com/api/?name=' . urlencode($this->name)
              . '&background=0284c7&color=fff';
    }

    // ─── Relationships ────────────────────────────────────────────────────────

    public function doctorProfile()
    {
        return $this->hasOne(DoctorProfile::class);
    }

    public function patientProfile()
    {
        return $this->hasOne(PatientProfile::class);
    }

    public function patientAppointments()
    {
        return $this->hasMany(Appointment::class, 'patient_id');
    }

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
    // أضف داخل class User بعد reviewsAsDoctor()

public function pharmacistProfile()
{
    return $this->hasOne(PharmacistProfile::class);
}

public function medications()
{
    return $this->hasMany(Medication::class, 'pharmacist_id');
}
}
