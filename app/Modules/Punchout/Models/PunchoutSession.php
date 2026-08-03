<?php

declare(strict_types=1);

namespace App\Modules\Punchout\Models;

use App\Modules\Punchout\Enums\PunchoutOperation;
use App\Modules\Punchout\Enums\PunchoutSessionStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * A single PunchOut session, from the moment Coupa's setup request is
 * accepted until the cart is transferred back or the session times out.
 * Every field the storefront's session rail needs (whose session this is,
 * how long is left) lives here; nothing about this model knows cXML
 * exists, that knowledge stays in the Cxml/ namespace.
 *
 * @property int $id
 * @property string $token
 * @property string $buyer_cookie
 * @property string $browser_form_post_url
 * @property string|null $buyer_user_email
 * @property string|null $buyer_unique_name
 * @property string|null $buyer_business_unit
 * @property string|null $buyer_country
 * @property PunchoutOperation $operation
 * @property PunchoutSessionStatus $status
 * @property Carbon $expires_at
 */
final class PunchoutSession extends Model
{
    protected $table = 'punchout_sessions';

    protected $fillable = [
        'token',
        'buyer_cookie',
        'browser_form_post_url',
        'buyer_user_email',
        'buyer_unique_name',
        'buyer_business_unit',
        'buyer_country',
        'operation',
        'status',
        'expires_at',
    ];

    protected function casts(): array
    {
        return [
            'operation' => PunchoutOperation::class,
            'status' => PunchoutSessionStatus::class,
            'expires_at' => 'datetime',
        ];
    }

    public function hasExpired(): bool
    {
        return $this->status === PunchoutSessionStatus::Expired
            || $this->expires_at->isPast();
    }

    public function isActive(): bool
    {
        return $this->status === PunchoutSessionStatus::Active && ! $this->hasExpired();
    }
}
