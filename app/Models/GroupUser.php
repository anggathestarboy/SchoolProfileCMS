<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GroupUser extends Model
{
    public $timestamps = false;

    protected $fillable = ['user_id', 'group_id'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
