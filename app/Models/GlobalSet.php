<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class GlobalSet extends Model
{
    protected $fillable = ['handle', 'title', 'settings'];

    protected $casts = [
        'settings' => 'array',
    ];

    public function getRouteKeyName(): string
    {
        return 'handle';
    }

    public function variables(): HasMany
    {
        return $this->hasMany(GlobalSetVariable::class, 'handle', 'handle');
    }

    public function inLocale(string $locale): ?GlobalSetVariable
    {
        return $this->variables()->where('locale', $locale)->first();
    }
}
