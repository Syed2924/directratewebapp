<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Permission extends Model
{
    protected $fillable = ['key', 'module', 'action', 'description', 'is_sensitive'];

    protected $casts = ['is_sensitive' => 'boolean'];

    public function roles()
    {
        return $this->belongsToMany(Role::class);
    }
}
