<?php

declare(strict_types=1);

namespace App\Modules\Storefront\Http\Controllers;

use App\Modules\Cart\Contracts\CartServiceInterface;
use App\Modules\Cart\Exceptions\CartNotFoundException;
use App\Modules\Cart\Exceptions\EmptyCartException;
use App\Modules\Catalog\Exceptions\ProductNotFoundException;
use App\Modules\Punchout\Contracts\PunchoutLoggerInterface;
use App\Modules\Punchout\Contracts\PunchoutProtocolInterface;
use App\Modules\Punchout\Contracts\SessionManagerInterface;
use App\Modules\Punchout\Data\OrderMessageData;
use App\Modules\Punchout\Enums\PunchoutMessageType;
use App\Modules\Punchout\Enums\PunchoutSessionStatus;
use App\Modules\Punchout\Exceptions\InvalidCredentialsException;
use App\Modules\Punchout\Models\PunchoutSession;
use App\Shared\Exceptions\DomainValidationException;
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

        // A reload or a retry within the grace window (see
        // PunchoutSessionStatus::Transferring): re-render the exact
        // message already built and logged rather than building and
        // sending Coupa a second, distinct PunchOutOrderMessage for the
        // same cart.
        if ($session->status === PunchoutSessionStatus::Transferring) {
            return $this->resumeExistingTransfer($session);
        }

        try {
            $cartSnapshot = $this->cart->buildTransferSnapshot($session->id);
        } catch (CartNotFoundException|EmptyCartException) {
            return $this->failed('Your cart is empty. Add at least one item before transferring back to Coupa.');
        } catch (ProductNotFoundException|DomainValidationException $exception) {
            // CartSnapshotFactory re-resolves pricing fresh per line at
            // transfer time (see its own docblock for why), which means a
            // product deactivated or repriced into a different currency
            // while the buyer had it in an open cart surfaces here, not
            // as a silent stale price. Named by SKU so the buyer has a
            // concrete next step instead of a generic failure.
            $sku = $exception->context()['sku'] ?? 'one of your items';

            Log::channel('punchout')->error('Cart transfer failed: a cart line could not be priced.', [
                'session_id' => $session->id,
                'sku' => $sku,
                'error' => $exception->getMessage(),
            ]);

            return $this->failed("We could not confirm current pricing for {$sku}. Please remove it from your cart and try again, or contact support if this continues.");
        }

        try {
            $identity = $this->sessions->resolveOutboundIdentity($session);
        } catch (InvalidCredentialsException $exception) {
            Log::channel('punchout')->error('Cart transfer failed: no active credential for outbound identity.', [
                'session_id' => $session->id,
                'error' => $exception->getMessage(),
            ]);

            return $this->failed('This punchout session could not be authenticated for transfer. Please contact support.');
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

        $this->sessions->markTransferring($session);

        return Inertia::render('Punchout/TransferInProgress', [
            'browserFormPostUrl' => $session->browser_form_post_url,
            'encodedCxml' => $orderMessageXml,
        ]);
    }

    private function resumeExistingTransfer(PunchoutSession $session): Response
    {
        $log = $this->logger->findLatestOutbound($session, PunchoutMessageType::OrderMessage);

        if ($log === null) {
            // Should be unreachable: Transferring is only ever entered
            // immediately after logOutbound() succeeds for this exact
            // message type. Fail safely rather than silently rebuilding a
            // second message if it somehow happens anyway.
            Log::channel('punchout')->error('Session is Transferring but no outbound PunchOutOrderMessage log was found to resume.', [
                'session_id' => $session->id,
            ]);

            return $this->failed('Something went wrong resuming your transfer. Please contact support.');
        }

        return Inertia::render('Punchout/TransferInProgress', [
            'browserFormPostUrl' => $session->browser_form_post_url,
            'encodedCxml' => $log->raw_payload,
        ]);
    }

    private function failed(string $reason): Response
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
