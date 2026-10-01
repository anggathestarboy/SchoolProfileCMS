<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PasswordActivationToken extends Model
{
    protected $table = 'password_activation_tokens';

    public $timestamps = false;

    public $incrementing = false;

    protected $fillable = ['email', 'token', 'created_at'];

    protected $casts = [
        'created_at' => 'datetime',
    ];
}
