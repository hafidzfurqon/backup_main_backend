<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Instance extends Model
{
    use HasFactory;

    protected $table = 'instances';

    protected $fillable = ['name', 'email', 'address'];
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'user_has_instance_models');
    }
}
