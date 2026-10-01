<?php

namespace App\Modules\Blueprints\Models;

use App\Modules\ActivityLog\Concerns\LogsActivity;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property string $handle
 * @property array $data
 */
class Fieldset extends Model
{
    use LogsActivity;

    protected $table = 'fieldsets';

    protected $fillable = ['handle', 'data'];

    protected $casts = [
        'data' => 'array',
    ];

    public function activityLabel(): string
    {
        return "fieldset '{$this->handle}'";
    }
}
