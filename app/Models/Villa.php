<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Villa extends Model
{
    use HasFactory;

    protected $fillable = [
        'name', 'description', 'category', 'num_rooms', 'status',
        'price_per_night', 'owner_id', 'notes',
    ];

    protected $casts = [
        'price_per_night' => 'decimal:2',
    ];

    public function owner()
    {
        return $this->belongsTo(Owner::class);
    }

    public function bookings()
    {
        return $this->hasMany(Booking::class);
    }

    public function activeBookings()
    {
        return $this->hasMany(Booking::class)->whereNotIn('status', ['cancelled']);
    }
}
