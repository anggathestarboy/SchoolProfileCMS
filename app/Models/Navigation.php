<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Navigation extends Model
{
    protected $fillable = ['handle', 'title', 'settings'];

    protected $casts = [
        'settings' => 'array',
    ];

    public function getRouteKeyName(): string
    {
        return 'handle';
    }

    /** The structural tree(s) belonging to this navigation, per locale. */
    public function trees(): HasMany
    {
        return $this->hasMany(Tree::class, 'handle', 'handle')->where('type', 'navigation');
    }
}
