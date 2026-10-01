<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AddonSetting extends Model
{
    protected $table = 'addon_settings';

    protected $primaryKey = 'addon';

    protected $keyType = 'string';

    public $incrementing = false;

    public $timestamps = false;

    protected $fillable = ['addon', 'settings'];

    protected $casts = [
        'settings' => 'array',
    ];
}
