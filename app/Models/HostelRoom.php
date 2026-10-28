<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class HostelRoom extends Model
{
    protected $fillable = ['room_number', 'room_type', 'capacity', 'status'];

    protected $casts = ['capacity' => 'integer', 'hostel_id' => 'integer'];

    public function hostel()
    {
        return $this->belongsTo(Hostel::class);
    }

    public function typeLabel(): string
    {
        return config("hostel.room_types.{$this->room_type}.0", ucfirst($this->room_type));
    }

    public function statusLabel(): string
    {
        return config("hostel.room_statuses.{$this->status}", ucfirst($this->status));
    }
}
