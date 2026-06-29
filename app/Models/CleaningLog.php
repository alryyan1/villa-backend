<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CleaningLog extends Model
{
    protected $fillable = ['villa_id', 'booking_id', 'user_id', 'cleaned_at', 'notes'];

    protected $casts = [
        'cleaned_at' => 'datetime',
    ];

    public function villa()
    {
        return $this->belongsTo(Villa::class);
    }

    public function booking()
    {
        return $this->belongsTo(Booking::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
