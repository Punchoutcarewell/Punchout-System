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
 * @property string|null $from_domain
 * @property string|null $from_identity
 * @property string|null $to_domain
 * @property string|null $to_identity
 * @property string|null $buyer_user_email
 * @property string|null $buyer_unique_name
 * @property string|null $buyer_business_unit
 * @property string|null $buyer_country
 * @property string|null $buyer_first_name
 * @property string|null $buyer_last_name
 * @property string|null $contact_name
 * @property string|null $contact_email
 * @property string|null $supplier_setup_url
 * @property PunchoutOperation $operation
 * @property bool $is_preview true only for a session SessionManager::startPreview() created from the Admin panel, never a real Coupa request
 * @property PunchoutSessionStatus $status
 * @property Carbon $expires_at
 * @property Carbon|null $transferring_at
 */
final class PunchoutSession extends Model
{
    protected $table = 'punchout_sessions';

    protected $fillable = [
        'token',
        'buyer_cookie',
        'browser_form_post_url',
        'from_domain',
        'from_identity',
        'to_domain',
        'to_identity',
        'buyer_user_email',
        'buyer_unique_name',
        'buyer_business_unit',
        'buyer_country',
        'buyer_first_name',
        'buyer_last_name',
        'contact_name',
        'contact_email',
        'supplier_setup_url',
        'operation',
        'is_preview',
        'status',
        'expires_at',
        'transferring_at',
    ];

    protected function casts(): array
    {
        return [
            'operation' => PunchoutOperation::class,
            'is_preview' => 'boolean',
            'status' => PunchoutSessionStatus::class,
            'expires_at' => 'datetime',
            'transferring_at' => 'datetime',
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

    /**
     * Whether the buyer is still inside the transfer grace window: the
     * form post to Coupa may or may not have actually landed, so a reload
     * or a retry within this window is treated as "still trying", not a
     * dead session.
     */
    public function isWithinTransferGrace(): bool
    {
        if ($this->status !== PunchoutSessionStatus::Transferring || $this->transferring_at === null) {
            return false;
        }

        $graceMinutes = (int) config('punchout.transfer_grace_minutes', 10);

        return $this->transferring_at->addMinutes($graceMinutes)->isFuture();
    }
}
