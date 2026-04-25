<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class MedicalRecord extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'patient_id',
        'doctor_id',
        'appointment_id',
        'record_type',   // lab_result|xray|mri|ct_scan|doctor_note|discharge_summary|other
        'title',
        'description',
        'file_path',
        'file_name',
        'file_size',
        'status',        // normal|abnormal|pending|critical
        'record_date',
        'is_visible_to_patient',
    ];

    protected $casts = [
        'record_date'            => 'date',
        'file_size'              => 'integer',
        'is_visible_to_patient'  => 'boolean',
    ];

    protected $appends = ['file_url', 'is_recent', 'record_type_label', 'status_label'];

    // ─── Scopes ───────────────────────────────────────────────────────────────

    public function scopeByPatient($q, $id)   { return $q->where('patient_id', $id); }
    public function scopeByDoctor($q, $id)    { return $q->where('doctor_id', $id); }
    public function scopeByType($q, $type)    { return $q->where('record_type', $type); }
    public function scopeByStatus($q, $s)     { return $q->where('status', $s); }

    public function scopeVisibleToPatient($q)
    {
        return $q->where('is_visible_to_patient', true);
    }

    public function scopeRecent($q, $days = 30)
    {
        return $q->where('record_date', '>=', now()->subDays($days));
    }

    // ─── Accessors ────────────────────────────────────────────────────────────

    public function getFileUrlAttribute(): ?string
    {
        return $this->file_path ? asset('storage/' . $this->file_path) : null;
    }

    public function getIsRecentAttribute(): bool
    {
        return $this->record_date && $this->record_date->gte(now()->subDays(30));
    }

    public function getRecordTypeLabelAttribute(): string
    {
        return [
            'lab_result'        => 'Lab Result',
            'xray'              => 'X-Ray',
            'mri'               => 'MRI',
            'ct_scan'           => 'CT Scan',
            'doctor_note'       => "Doctor's Note",
            'discharge_summary' => 'Discharge Summary',
            'other'             => 'Other',
        ][$this->record_type] ?? ucfirst(str_replace('_', ' ', $this->record_type ?? ''));
    }

    public function getStatusLabelAttribute(): string
    {
        return [
            'normal'   => 'Normal',
            'abnormal' => 'Abnormal',
            'pending'  => 'Pending Review',
            'critical' => 'Critical',
        ][$this->status] ?? ucfirst($this->status ?? '');
    }

    // ─── Relationships ────────────────────────────────────────────────────────

    public function patient()
    {
        return $this->belongsTo(User::class, 'patient_id');
    }

    public function doctor()
    {
        return $this->belongsTo(User::class, 'doctor_id');
    }

    public function appointment()
    {
        return $this->belongsTo(Appointment::class);
    }
}