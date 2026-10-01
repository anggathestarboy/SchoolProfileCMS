<?php

namespace App\Modules\Forms\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One submitted response to a Form. The primary key is a decimal
 * microtime-based string (mirroring Statamic's submission ids) rather than
 * an auto-increment integer, so ids sort chronologically without a
 * dedicated "submitted_at" column.
 *
 * @property string $id
 * @property string $form
 * @property array|null $data
 */
class FormSubmission extends Model
{
    protected $table = 'form_submissions';

    protected $primaryKey = 'id';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = ['id', 'form', 'data'];

    protected $casts = [
        'data' => 'array',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $submission): void {
            // ponytail: microtime-based id, collisions possible within the
            // same 0.1ms on very high-traffic forms — swap for a proper
            // sequence/uuid if that ever becomes a real submission volume.
            $submission->id ??= number_format(microtime(true), 4, '.', '');
        });
    }

    public function formModel(): BelongsTo
    {
        return $this->belongsTo(Form::class, 'form', 'handle');
    }
}
