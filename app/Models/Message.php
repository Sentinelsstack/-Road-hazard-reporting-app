<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Message extends Model
{
    protected $fillable = [
        'hazard_id',
        'user_id',
        'admin_id',
        'message',
        'is_from_admin',
    ];

    protected function casts(): array
    {
        return [
            'is_from_admin' => 'boolean',
        ];
    }

    public function hazard()
    {
        return $this->belongsTo(Hazard::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function admin()
    {
        return $this->belongsTo(User::class, 'admin_id');
    }
}
