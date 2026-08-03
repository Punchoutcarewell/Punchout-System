<?php

declare(strict_types=1);

namespace App\Modules\Punchout\Models;

use App\Modules\Punchout\Enums\PunchoutMessageDirection;
use App\Modules\Punchout\Enums\PunchoutMessageType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * One row per cXML payload, in either direction. Written by PunchoutLogger
 * before parsing is attempted, so a parser bug can never lose the raw
 * evidence of what Coupa actually sent. The shared secret is redacted
 * before the row is written, never after.
 *
 * @property int $id
 * @property int|null $session_id
 * @property PunchoutMessageDirection $direction
 * @property PunchoutMessageType $message_type
 * @property int|null $http_status
 * @property string $raw_payload
 * @property string|null $error
 * @property Carbon $created_at
 */
final class PunchoutLog extends Model
{
    public $timestamps = false;

    protected $table = 'punchout_logs';

    protected $fillable = [
        'session_id',
        'direction',
        'message_type',
        'http_status',
        'raw_payload',
        'error',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'session_id' => 'integer',
            'direction' => PunchoutMessageDirection::class,
            'message_type' => PunchoutMessageType::class,
            'created_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<PunchoutSession, $this>
     */
    public function session(): BelongsTo
    {
        return $this->belongsTo(PunchoutSession::class, 'session_id');
    }
}
