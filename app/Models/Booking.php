<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Booking extends Model
{
    use HasFactory;

    protected $fillable = [
        'villa_id', 'guest_id', 'user_id', 'num_guests', 'check_in', 'check_in_time', 'check_out',
        'nights', 'status', 'total_amount', 'paid_amount', 'payment_status',
        'payment_notes', 'notes', 'cancelled_at', 'cancellation_reason',
    ];

    protected $casts = [
        'check_in'      => 'date',
        'check_out'     => 'date',
        'cancelled_at'  => 'datetime',
        'total_amount'  => 'decimal:2',
        'paid_amount'   => 'decimal:2',
    ];

    public function villa()
    {
        return $this->belongsTo(Villa::class);
    }

    public function guest()
    {
        return $this->belongsTo(Guest::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function payments()
    {
        return $this->hasMany(Payment::class);
    }
}
