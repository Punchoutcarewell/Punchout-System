<?php

declare(strict_types=1);

namespace App\Modules\Punchout\Services;

use App\Modules\Punchout\Contracts\SessionManagerInterface;
use App\Modules\Punchout\Data\OutboundIdentity;
use App\Modules\Punchout\Data\SetupRequestData;
use App\Modules\Punchout\Enums\PunchoutEnvironment;
use App\Modules\Punchout\Enums\PunchoutOperation;
use App\Modules\Punchout\Enums\PunchoutSessionStatus;
use App\Modules\Punchout\Exceptions\InvalidCredentialsException;
use App\Modules\Punchout\Models\PunchoutCredential;
use App\Modules\Punchout\Models\PunchoutSession;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\URL;
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
            'from_domain' => $data->fromDomain,
            'from_identity' => $data->fromIdentity,
            'to_domain' => $data->toDomain,
            'to_identity' => $data->toIdentity,
            'buyer_user_email' => $data->extrinsic('UserEmail'),
            'buyer_unique_name' => $data->extrinsic('UniqueName'),
            'buyer_business_unit' => $data->extrinsic('BusinessUnit'),
            'buyer_country' => $data->extrinsic('Country'),
            'buyer_first_name' => $data->extrinsic('FirstName'),
            'buyer_last_name' => $data->extrinsic('LastName'),
            'contact_name' => $data->contactName,
            'contact_email' => $data->contactEmail,
            'supplier_setup_url' => $data->supplierSetupUrl,
            'operation' => $data->operation,
            'status' => PunchoutSessionStatus::Active,
            'expires_at' => Carbon::now()->addMinutes((int) config('punchout.session_ttl_minutes', 60)),
        ]);
    }

    public function startPreview(PunchoutCredential $credential, string $label): PunchoutSession
    {
        return PunchoutSession::query()->create([
            'token' => Str::random(64),
            'buyer_cookie' => 'admin-preview-'.Str::uuid(),
            // Same-app route, not a real Coupa checkout URL: an admin
            // clicking all the way through to "Transfer cart to Coupa"
            // during a preview lands on a page confirming what would have
            // been sent, rather than a browser error from posting to a
            // URL that does not exist.
            'browser_form_post_url' => URL::route('admin.punchout-preview.complete'),
            'from_domain' => $credential->from_domain,
            'from_identity' => $credential->from_identity,
            'to_domain' => $credential->to_domain,
            'to_identity' => $credential->to_identity,
            'buyer_unique_name' => $label,
            'buyer_business_unit' => 'Admin preview',
            'operation' => PunchoutOperation::Create,
            'is_preview' => true,
            'status' => PunchoutSessionStatus::Active,
            'expires_at' => Carbon::now()->addMinutes((int) config('punchout.preview_ttl_minutes', 30)),
        ]);
    }

    public function startFromSharedSecret(PunchoutCredential $credential): PunchoutSession
    {
        return PunchoutSession::query()->create([
            'token' => Str::random(64),
            'buyer_cookie' => 'link-'.Str::uuid(),
            'browser_form_post_url' => (string) $credential->browser_form_post_url,
            'from_domain' => $credential->from_domain,
            'from_identity' => $credential->from_identity,
            'to_domain' => $credential->to_domain,
            'to_identity' => $credential->to_identity,
            'buyer_unique_name' => $credential->to_identity,
            'operation' => PunchoutOperation::Create,
            'is_preview' => false,
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

        if ($session->status === PunchoutSessionStatus::Transferring) {
            if ($session->isWithinTransferGrace()) {
                return $session;
            }

            // The grace window lapsed with no retry: this is as close to
            // "confirmed" as this app can ever get, since the cXML
            // PunchOut protocol has no callback for it. See
            // PunchoutSessionStatus::Transferring.
            $session->update(['status' => PunchoutSessionStatus::Transferred]);

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

    public function markTransferring(PunchoutSession $session): void
    {
        $session->update(['status' => PunchoutSessionStatus::Transferring, 'transferring_at' => Carbon::now()]);
        $this->boundSession = $session->refresh();
    }

    public function markTransferred(PunchoutSession $session): void
    {
        $session->update(['status' => PunchoutSessionStatus::Transferred]);
        $this->boundSession = $session->refresh();
    }

    public function resolveOutboundIdentity(PunchoutSession $session): OutboundIdentity
    {
        $credential = PunchoutCredential::query()
            ->where('environment', PunchoutEnvironment::current())
            ->where('from_domain', $session->from_domain)
            ->where('from_identity', $session->from_identity)
            ->where('to_domain', $session->to_domain)
            ->where('to_identity', $session->to_identity)
            ->where('is_active', true)
            ->first();

        if ($credential === null) {
            throw InvalidCredentialsException::withContext(
                'No active credential matches this session, cannot build an outbound identity.',
                [
                    'session_id' => $session->id,
                    'from_domain' => $session->from_domain,
                    'from_identity' => $session->from_identity,
                    'to_domain' => $session->to_domain,
                    'to_identity' => $session->to_identity,
                ],
            );
        }

        return new OutboundIdentity(
            fromDomain: $credential->to_domain,
            fromIdentity: $credential->to_identity,
            toDomain: (string) $session->from_domain,
            toIdentity: (string) $session->from_identity,
            senderDomain: $credential->sender_domain,
            senderIdentity: $credential->sender_identity,
            deploymentMode: $credential->environment->value === 'production' ? 'production' : 'test',
        );
    }
}
