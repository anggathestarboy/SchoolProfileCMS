<?php

namespace App\Modules\Blueprints\Models;

use App\Modules\ActivityLog\Concerns\LogsActivity;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property string|null $namespace
 * @property string $handle
 * @property array $data
 */
class Blueprint extends Model
{
    use LogsActivity;

    protected $table = 'blueprints';

    protected $fillable = ['namespace', 'handle', 'data'];

    protected $casts = [
        'data' => 'array',
    ];

    public function activityLabel(): string
    {
        return "blueprint '{$this->handle}'";
    }
}
