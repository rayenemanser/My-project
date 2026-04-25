<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class PatientProfile extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'blood_type',
        'height',
        'weight',
        'allergies',
        'chronic_conditions',
        'current_medications',
        'medical_history',
        'emergency_contact_name',
        'emergency_contact_phone',
        'emergency_contact_relation',
        'insurance_provider',
        'insurance_number',
        'wilaya',
        'postal_code',
        'occupation',
        'marital_status',
        'last_visit',
        'primary_doctor_id',
    ];

    protected $casts = [
        'allergies'           => 'array',
        'chronic_conditions'  => 'array',
        'current_medications' => 'array',
        'last_visit'          => 'date',
    ];

    // ─── Relationships ────────────────────────────────────────────────────────

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function primaryDoctor()
    {
        return $this->belongsTo(User::class, 'primary_doctor_id');
    }

    // ─── Accessors ────────────────────────────────────────────────────────────

    public function getBmiAttribute(): ?float
    {
        if (!$this->height || !$this->weight) return null;
        $h = $this->height / 100;
        return round($this->weight / ($h * $h), 1);
    }
}