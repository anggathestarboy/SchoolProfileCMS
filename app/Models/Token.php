<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Token extends Model
{
    protected $fillable = ['token', 'handler', 'data', 'expire_at'];

    protected $casts = [
        'data' => 'array',
        'expire_at' => 'datetime',
    ];

    public function isExpired(): bool
    {
        return $this->expire_at?->isPast() ?? false;
    }
}
