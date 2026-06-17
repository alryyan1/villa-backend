<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Owner extends Model
{
    use HasFactory;

    protected $fillable = [
        'name', 'phone', 'whatsapp_number', 'email', 'address', 'notes',
    ];

    public function villas()
    {
        return $this->hasMany(Villa::class);
    }
}
