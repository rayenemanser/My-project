<?php
// app/Models/PharmacistProfile.php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class PharmacistProfile extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'user_id',
        'pharmacy_name',
        'license_number',
        'pharmacist_license',
        'phone',
        'address',
        'city',
        'wilaya',
        'qualifications',
        'experience_years',
        'certifications',
        'insurance_accepted',
        'specialized_equipment',
        'additional_notes',
        'is_verified',
        'is_available',
    ];

    protected $casts = [
        'is_verified'  => 'boolean',
        'is_available' => 'boolean',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
