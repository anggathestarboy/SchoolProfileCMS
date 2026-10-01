<?php

namespace App\Modules\Structure\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property string $handle
 * @property string $type
 * @property string|null $locale
 * @property array $tree
 * @property array|null $settings
 */
class Tree extends Model
{
    protected $table = 'trees';

    protected $fillable = ['handle', 'type', 'locale', 'tree', 'settings'];

    protected $casts = [
        'tree' => 'array',
        'settings' => 'array',
    ];
}
