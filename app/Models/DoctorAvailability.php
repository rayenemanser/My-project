<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DoctorAvailability extends Model
{
    protected $fillable = [
        'doctor_id',
        'day_of_week',   // 0=Sunday, 1=Monday ... 6=Saturday
        'start_time',
        'end_time',
        'is_active',
    ];

    protected $casts = [
        'is_active'   => 'boolean',
        'day_of_week' => 'integer',
    ];

    protected static array $dayNames = [
        0 => 'Sunday',   1 => 'Monday', 2 => 'Tuesday',
        3 => 'Wednesday',4 => 'Thursday',5 => 'Friday', 6 => 'Saturday',
    ];

    // ─── Accessors ────────────────────────────────────────────────────────────

    public function getDayNameAttribute(): string
    {
        return self::$dayNames[$this->day_of_week] ?? '';
    }

    // ─── Relationships ────────────────────────────────────────────────────────

    public function doctor()
    {
        return $this->belongsTo(User::class, 'doctor_id');
    }

    // ─── Methods ──────────────────────────────────────────────────────────────

    public function generateSlots(int $duration = 30): array
    {
        $slots = [];
        $start = strtotime($this->start_time);
        $end   = strtotime($this->end_time);

        while ($start + ($duration * 60) <= $end) {
            $slots[] = date('H:i', $start);
            $start  += $duration * 60;
        }

        return $slots;
    }
}