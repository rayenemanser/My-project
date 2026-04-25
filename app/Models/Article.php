<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Support\Str;

class Article extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'doctor_id',
        'title',
        'slug',
        'excerpt',
        'content',
        'cover_image',
        'specialty',
        'tags',
        'status',      // draft|published|archived
        'views',
        'published_at',
    ];

    protected $casts = [
        'tags'         => 'array',
        'published_at' => 'datetime',
        'views'        => 'integer',
    ];

    // ─── Boot ─────────────────────────────────────────────────────────────────

    protected static function boot(): void
    {
        parent::boot();
        static::creating(function ($a) {
            $a->slug ??= Str::slug($a->title) . '-' . Str::random(5);
        });
    }

    // ─── Scopes ───────────────────────────────────────────────────────────────

    public function scopePublished($q)
    {
        return $q->where('status', 'published');
    }

    public function scopeBySpecialty($q, string $specialty)
    {
        return $q->where('specialty', $specialty);
    }

    // ─── Methods ──────────────────────────────────────────────────────────────

    public function incrementViews(): void
    {
        $this->increment('views');
    }

    public function publish(): void
    {
        $this->update(['status' => 'published', 'published_at' => now()]);
    }

    // ─── Relationships ────────────────────────────────────────────────────────

    public function doctor()
    {
        return $this->belongsTo(User::class, 'doctor_id');
    }
}