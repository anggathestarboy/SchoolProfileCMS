<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Tree extends Model
{
    protected $fillable = ['handle', 'type', 'locale', 'tree', 'settings'];

    protected $casts = [
        'tree' => 'array',
        'settings' => 'array',
    ];

    public function scopeForCollections($query)
    {
        return $query->where('type', 'collection');
    }

    public function scopeForNavigations($query)
    {
        return $query->where('type', 'navigation');
    }

    public function collection(): BelongsTo
    {
        return $this->belongsTo(Collection::class, 'handle', 'handle');
    }

    public function navigation(): BelongsTo
    {
        return $this->belongsTo(Navigation::class, 'handle', 'handle');
    }
}
