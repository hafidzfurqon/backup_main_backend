<?php

namespace App\Models;

use App\Casts\HashId;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Folder extends Model
{
    use HasFactory;

    protected $fillable = [
        'nanoid',
        'name',
        'description',
        'type',
        'user_id',
        'parent_id'
    ];

    protected static function boot()
    {
        parent::boot();

        // Automatically generate a NanoID when creating a new folder
        static::creating(function ($model) {
            if (empty($model->nanoid)) {
                $model->nanoid = self::generateNanoId();
            };

            if (empty($model->type)) {
                $model->type = 'folder';
            };
        });
    }

    public static function generateNanoId($size = 21)
    {
        return (new \Hidehalo\Nanoid\Client())->generateId($size);
    }

    public function files()
    {
        return $this->hasMany(File::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function parentFolder()
    {
        return $this->belongsTo(Folder::class, 'parent_id');
    }

    public function subfolders()
    {
        return $this->hasMany(Folder::class, 'parent_id');
    }

    public function userFolderPermissions()
    {
        return $this->hasMany(UserFolderPermission::class);
    }

    // Add this method to define the many-to-many relationship with tags
    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(Tags::class, 'folder_has_tags')->withTimestamps(); // menggunakan tabel pivot untuk menyalakan otomatisasi timestamp().
    }

     // Relasi many-to-many dengan InstanceModel
     public function instances(): BelongsToMany
     {
         return $this->belongsToMany(Instance::class, 'folder_has_instances')->withTimestamps(); // menggunakan tabel pivot untuk menyalakan otomatisasi timestamp().
     }
}
