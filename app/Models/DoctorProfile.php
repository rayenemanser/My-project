<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class DoctorProfile extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'specialty',
        'sub_specialty',
        'license_number',
        'experience_years',
        'bio',
        'working_hours',
        'consultation_fee',
        'consultation_duration',
        'is_available',
        'languages',
        'education',
        'experience',
        'clinic_name',
        'clinic_address',
        'clinic_city',
        'rating',
        'total_reviews',
    ];

    protected $casts = [
        'working_hours'         => 'array',
        'languages'             => 'array',
        'education'             => 'array',
        'experience'            => 'array',
        'is_available'          => 'boolean',
        'consultation_fee'      => 'decimal:2',
        'consultation_duration' => 'integer',
        'experience_years'      => 'integer',
        'rating'                => 'decimal:1',
        'total_reviews'         => 'integer',
    ];

    // ─── Relationships ────────────────────────────────────────────────────────

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function availabilities()
    {
        return $this->hasMany(DoctorAvailability::class, 'doctor_id', 'user_id');
    }

    // ─── Accessors ────────────────────────────────────────────────────────────

    public function getFullNameAttribute(): string
    {
        return 'Dr. ' . $this->user->name;
    }

    // ─── Methods ──────────────────────────────────────────────────────────────

    public function isAvailableAt(\Carbon\Carbon $datetime): bool
    {
        if (!$this->working_hours) return false;

        $day  = strtolower($datetime->format('l'));
        $time = $datetime->format('H:i');

        if (!isset($this->working_hours[$day])) return false;

        $slot = $this->working_hours[$day];
        return $time >= $slot['start'] && $time <= $slot['end'];
    }

    public function updateRating(): void
    {
        $avg   = Review::where('doctor_id', $this->user_id)->avg('rating');
        $count = Review::where('doctor_id', $this->user_id)->count();
        $this->update([
            'rating'        => round($avg ?? 0, 1),
            'total_reviews' => $count,
        ]);
    }
}