<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

/**
 * @property int $id
 * @property string $name
 * @property string|null $phone
 * @property string|null $whatsapp_number
 * @property string|null $email
 * @property string|null $address
 * @property string|null $notes
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Villa> $villas
 * @property-read int|null $villas_count
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Owner newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Owner newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Owner query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Owner whereAddress($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Owner whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Owner whereEmail($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Owner whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Owner whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Owner whereNotes($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Owner wherePhone($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Owner whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Owner whereWhatsappNumber($value)
 * @mixin \Eloquent
 */
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
