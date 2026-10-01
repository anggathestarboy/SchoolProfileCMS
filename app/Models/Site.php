<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Site extends Model
{
    protected $fillable = ['handle', 'name', 'url', 'locale', 'lang', 'attributes', 'order'];

    protected $casts = [
        'attributes' => 'array',
    ];

    public function getRouteKeyName(): string
    {
        return 'handle';
    }

    public function entries(): HasMany
    {
        return $this->hasMany(Entry::class, 'site', 'handle');
    }

    public function sitemaps(): HasMany
    {
        return $this->hasMany(Sitemap::class, 'site', 'handle');
    }
}
