<?php

namespace App\Models;

use App\Models\Concerns\HasFlexibleData;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FormSubmission extends Model
{
    use HasFlexibleData;

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = ['form', 'data'];

    protected $casts = [
        'data' => 'array',
    ];

    public function formModel(): BelongsTo
    {
        return $this->belongsTo(Form::class, 'form', 'handle');
    }
}
