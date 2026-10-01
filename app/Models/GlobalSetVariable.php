<?php

namespace App\Models;

use App\Models\Concerns\HasFlexibleData;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GlobalSetVariable extends Model
{
    // use HasFlexibleData;

    protected $fillable = ['handle', 'locale', 'data'];

    protected $casts = [
        'data' => 'array',
    ];

    public function globalSet(): BelongsTo
    {
        return $this->belongsTo(GlobalSet::class, 'handle', 'handle');
    }
}
