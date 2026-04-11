<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MedicalRecord extends Model
{
    protected $fillable = [
        'patient_id', 'doctor_id', 'appointment_id',
        'record_type', 'title', 'description',
        'file_path', 'file_name', 'file_size',
        'status', 'record_date'
    ];

    protected $casts = [
        'record_date' => 'date',
        'file_size' => 'integer',
    ];

    protected $appends = ['file_url', 'is_recent'];

    // ========== العلاقات ==========
    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }

    public function doctor(): BelongsTo
    {
        return $this->belongsTo(Doctor::class);
    }

    public function appointment(): BelongsTo
    {
        return $this->belongsTo(Appointment::class);
    }

    // ========== التوابع المساعدة ==========
    public function getFileUrlAttribute(): ?string
    {
        return $this->file_path ? asset('storage/' . $this->file_path) : null;
    }

    public function getIsRecentAttribute(): bool
    {
        return $this->record_date->gte(now()->subDays(30));
    }

    public function getRecordTypeLabelAttribute(): string
    {
        $labels = [
            'lab_result' => 'Lab Result',
            'prescription' => 'Prescription',
            'xray' => 'X-Ray',
            'mri' => 'MRI',
            'ct_scan' => 'CT Scan',
            'doctor_note' => "Doctor's Note",
            'discharge_summary' => 'Discharge Summary',
            'other' => 'Other'
        ];

        return $labels[$this->record_type] ?? ucfirst(str_replace('_', ' ', $this->record_type));
    }

    public function getStatusLabelAttribute(): string
    {
        $labels = [
            'normal' => 'Normal',
            'abnormal' => 'Abnormal',
            'pending' => 'Pending Review',
            'critical' => 'Critical'
        ];

        return $labels[$this->status] ?? ucfirst($this->status);
    }

    public function getStatusColorAttribute(): string
    {
        $colors = [
            'normal' => 'success',
            'abnormal' => 'warning',
            'pending' => 'info',
            'critical' => 'error'
        ];

        return $colors[$this->status] ?? 'default';
    }

    // ========== النطاقات ==========
    public function scopeByPatient($query, $patientId)
    {
        return $query->where('patient_id', $patientId);
    }

    public function scopeByType($query, $type)
    {
        return $query->where('record_type', $type);
    }

    public function scopeRecent($query, $days = 30)
    {
        return $query->where('record_date', '>=', now()->subDays($days));
    }

    public function scopeByStatus($query, $status)
    {
        return $query->where('status', $status);
    }
}