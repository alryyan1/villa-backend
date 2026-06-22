<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

/**
 * @property int $id
 * @property string $name
 * @property string|null $description
 * @property string|null $category
 * @property int $num_rooms
 * @property string $status
 * @property numeric $price_per_night
 * @property int $owner_id
 * @property bool $is_managed
 * @property string|null $notes
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Booking> $activeBookings
 * @property-read int|null $active_bookings_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Booking> $bookings
 * @property-read int|null $bookings_count
 * @property-read \App\Models\Owner $owner
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Villa newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Villa newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Villa query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Villa whereCategory($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Villa whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Villa whereDescription($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Villa whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Villa whereIsManaged($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Villa whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Villa whereNotes($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Villa whereNumRooms($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Villa whereOwnerId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Villa wherePricePerNight($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Villa whereStatus($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Villa whereUpdatedAt($value)
 * @mixin \Eloquent
 */
class Villa extends Model
{
    use HasFactory;

    protected $fillable = [
        'name', 'description', 'category', 'num_rooms', 'status',
        'price_per_night', 'owner_id', 'is_managed', 'notes',
    ];

    protected $casts = [
        'price_per_night' => 'decimal:2',
        'is_managed'      => 'boolean',
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
