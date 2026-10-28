<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UserScope extends Model
{
    public const TYPES = ['global', 'district', 'office', 'center', 'hostel'];

    protected $fillable = ['user_id', 'scope_type', 'scope_id'];

    protected $casts = ['user_id' => 'integer', 'scope_id' => 'integer'];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
