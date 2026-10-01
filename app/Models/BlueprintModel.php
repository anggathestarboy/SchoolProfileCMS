<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Named BlueprintModel to avoid clashing with Illuminate\Database\Schema\Blueprint.
 */
class BlueprintModel extends Model
{
    protected $table = 'blueprints';

    protected $fillable = ['namespace', 'handle', 'data'];

    protected $casts = [
        'data' => 'array',
    ];
}
