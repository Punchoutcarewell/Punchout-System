<?php

declare(strict_types=1);

namespace App\Modules\Punchout\Services;

use App\Modules\Punchout\Contracts\SessionManagerInterface;
use App\Modules\Punchout\Data\SetupRequestData;
use App\Modules\Punchout\Enums\PunchoutSessionStatus;
use App\Modules\Punchout\Models\PunchoutSession;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * The only class permitted to create, resolve, or transition a
 * PunchoutSession. Registered as a singleton in PunchoutServiceProvider so
 * bind()/current() hold the same instance across a single request.
 */
final class SessionManager implements SessionManagerInterface
{
    private ?PunchoutSession $boundSession = null;

    public function start(SetupRequestData $data): PunchoutSession
    {
        return PunchoutSession::query()->create([
            'token' => Str::random(64),
            'buyer_cookie' => $data->buyerCookie,
            'browser_form_post_url' => $data->browserFormPostUrl,
            'buyer_user_email' => $data->extrinsic('UserEmail'),
            'buyer_unique_name' => $data->extrinsic('UniqueName'),
            'buyer_business_unit' => $data->extrinsic('BusinessUnit'),
            'buyer_country' => $data->extrinsic('Country'),
            'operation' => $data->operation,
            'status' => PunchoutSessionStatus::Active,
            'expires_at' => Carbon::now()->addMinutes((int) config('punchout.session_ttl_minutes', 60)),
        ]);
    }

    public function resolve(string $token): ?PunchoutSession
    {
        $session = PunchoutSession::query()->where('token', $token)->first();

        if ($session === null) {
            return null;
        }

        if ($session->hasExpired()) {
            if ($session->status === PunchoutSessionStatus::Active) {
                $session->update(['status' => PunchoutSessionStatus::Expired]);
            }

            return null;
        }

        if ($session->status !== PunchoutSessionStatus::Active) {
            return null;
        }

        return $session;
    }

    public function bind(PunchoutSession $session): void
    {
        $this->boundSession = $session;
    }

    public function current(): ?PunchoutSession
    {
        return $this->boundSession;
    }

    public function markTransferred(PunchoutSession $session): void
    {
        $session->update(['status' => PunchoutSessionStatus::Transferred]);
        $this->boundSession = $session->refresh();
    }
}
