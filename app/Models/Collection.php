<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Collection extends Model
{
    protected $fillable = ['handle', 'title', 'settings'];

    protected $casts = [
        'settings' => 'array',
    ];

    public function getRouteKeyName(): string
    {
        return 'handle';
    }

    public function entries(): HasMany
    {
        return $this->hasMany(Entry::class, 'collection', 'handle');
    }

    public function sitemaps(): HasMany
    {
        return $this->hasMany(Sitemap::class, 'collection', 'handle');
    }
}
