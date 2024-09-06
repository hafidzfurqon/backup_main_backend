<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class News extends Model
{
    use HasFactory;

    protected $fillable = [
        'created_by',
        'url_thumbnail',
        'slug',
        'title',
        'content',
        'viewer',
        'status'
    ];

    // Relasi ke model User
    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function newsTags(): BelongsToMany
    {
        return $this->belongsToMany(NewsTag::class, 'news_has_tags')->withTimestamps(); // menggunakan tabel pivot untuk menyalakan otomatisasi timestamp().
    }
}
