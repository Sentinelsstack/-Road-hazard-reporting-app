<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Hazard extends Model
{
    use HasFactory;

    /**
     * The only hazard types accepted by the API. Enforced server-side so
     * client-supplied type strings can never reach validation-passthrough
     * sinks (CSS classes, chart labels) untrusted.
     */
    public const TYPES = [
        'pothole',
        'fallen_tree',
        'flooding',
        'broken_traffic_light',
        'road_damage',
        'landslide',
        'manhole_cover_missing',
        'road_accident',
    ];

    public const STATUSES = ['pending', 'confirmed', 'resolved', 'dismissed'];

    protected $fillable = [
        'type',
        'description',
        'lat',
        'lng',
        'image_path',
        'status',
        'user_id',
    ];

    protected function casts(): array
    {
        return [
            'lat' => 'decimal:7',
            'lng' => 'decimal:7',
        ];
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function messages()
    {
        return $this->hasMany(Message::class);
    }
}
