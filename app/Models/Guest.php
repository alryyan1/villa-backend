<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Guest extends Model
{
    use HasFactory;

    protected $fillable = [
        'name', 'phone', 'id_number', 'nationality', 'notes',
    ];

    public function bookings()
    {
        return $this->hasMany(Booking::class);
    }
}
