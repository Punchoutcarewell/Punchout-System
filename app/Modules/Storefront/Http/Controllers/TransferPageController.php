<?php

declare(strict_types=1);

namespace App\Modules\Storefront\Http\Controllers;

use App\Modules\Cart\Contracts\CartServiceInterface;
use App\Modules\Cart\Exceptions\CartNotFoundException;
use App\Modules\Cart\Exceptions\EmptyCartException;
use App\Modules\Punchout\Contracts\PunchoutLoggerInterface;
use App\Modules\Punchout\Contracts\PunchoutProtocolInterface;
use App\Modules\Punchout\Contracts\SessionManagerInterface;
use App\Modules\Punchout\Data\OrderMessageData;
use App\Modules\Punchout\Enums\PunchoutMessageType;
use App\Modules\Punchout\Exceptions\InvalidCredentialsException;
use App\Modules\Punchout\Models\PunchoutSession;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Inertia\Response;
use RuntimeException;

/**
 * Where Cart's snapshot and Punchout's message builder finally meet: this
 * is the one place in the whole application that builds an outbound
 * PunchOutOrderMessage. This module itself has no cXML knowledge, it only
 * orchestrates the two modules that do.
 */
final class TransferPageController
{
    public function __construct(
        private readonly CartServiceInterface $cart,
        private readonly SessionManagerInterface $sessions,
        private readonly PunchoutProtocolInterface $protocol,
        private readonly PunchoutLoggerInterface $logger,
    ) {}

    public function store(): Response
    {
        $session = $this->requireSession();

        try {
            $cartSnapshot = $this->cart->buildTransferSnapshot($session->id);
        } catch (CartNotFoundException|EmptyCartException) {
            return $this->failed(
                $session,
                'Your cart is empty. Add at least one item before transferring back to Coupa.',
            );
        }

        try {
            $identity = $this->sessions->resolveOutboundIdentity($session);
        } catch (InvalidCredentialsException $exception) {
            Log::channel('punchout')->error('Cart transfer failed: no active credential for outbound identity.', [
                'session_id' => $session->id,
                'error' => $exception->getMessage(),
            ]);

            return $this->failed(
                $session,
                'This punchout session could not be authenticated for transfer. Please contact support.',
            );
        }

        $orderMessageXml = $this->protocol->buildOrderMessage(new OrderMessageData(
            fromDomain: $identity->fromDomain,
            fromIdentity: $identity->fromIdentity,
            toDomain: $identity->toDomain,
            toIdentity: $identity->toIdentity,
            senderDomain: $identity->senderDomain,
            senderIdentity: $identity->senderIdentity,
            buyerCookie: $session->buyer_cookie,
            cart: $cartSnapshot,
            deploymentMode: $identity->deploymentMode,
        ));

        $this->logger->logOutbound(PunchoutMessageType::OrderMessage, $orderMessageXml, $session);

        $this->sessions->markTransferred($session);

        return Inertia::render('Punchout/TransferInProgress', [
            'browserFormPostUrl' => $session->browser_form_post_url,
            'encodedCxml' => $orderMessageXml,
        ]);
    }

    private function failed(PunchoutSession $session, string $reason): Response
    {
        return Inertia::render('Punchout/TransferFailed', [
            'reason' => $reason,
        ]);
    }

    private function requireSession(): PunchoutSession
    {
        $session = $this->sessions->current();

        if ($session === null) {
            // Unreachable in practice: punchout.require-session already
            // redirects before this controller runs. Guarded anyway
            // rather than trusting route wiring never changes.
            throw new RuntimeException('TransferPageController reached with no bound punchout session.');
        }

        return $session;
    }
}
