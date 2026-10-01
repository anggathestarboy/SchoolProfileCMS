<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Fieldset extends Model
{
    protected $fillable = ['handle', 'data'];

    protected $casts = [
        'data' => 'array',
    ];

    public function getRouteKeyName(): string
    {
        return 'handle';
    }
}
