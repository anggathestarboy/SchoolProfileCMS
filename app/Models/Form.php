<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Form extends Model
{
    protected $fillable = ['handle', 'title', 'settings'];

    protected $casts = [
        'settings' => 'array',
    ];

    public function getRouteKeyName(): string
    {
        return 'handle';
    }

    public function submissions(): HasMany
    {
        return $this->hasMany(FormSubmission::class, 'form', 'handle');
    }
}
