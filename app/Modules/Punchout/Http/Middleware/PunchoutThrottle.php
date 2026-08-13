<?php

declare(strict_types=1);

namespace App\Modules\Punchout\Http\Middleware;

use App\Modules\Punchout\Cxml\XmlSecurity;
use App\Modules\Punchout\Cxml\XPathReader;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

/**
 * Rate limits /api/punchout/setup and /api/punchout/order without going through
 * Laravel's standard throttle: middleware, for two reasons neither of
 * which that middleware can do:
 *
 * 1. Both endpoints promise Coupa well-formed cXML back for every
 *    outcome, success or failure (see SetupController/OrderRequestController's
 *    own Throwable nets). ThrottleRequests runs before the controller and
 *    returns its own JSON/HTML 429, which breaks that promise on exactly
 *    the path a real deployment is most likely to hit it under normal
 *    Coupa traffic volume (see 2).
 * 2. Keyed on the cXML Header's From identity (the caller's own identity,
 *    the same one CredentialValidator authenticates per-request, see H2)
 *    rather than solely $request->ip(). Header/To is always this
 *    supplier's own identity and is constant on every request, so it
 *    cannot distinguish one caller from another; Header/From is the
 *    identity that varies per buyer instance.
 *
 * The limit itself (30/min) is unchanged from the RateLimiter::for()
 * definition this replaces: it has not been confirmed against real Coupa
 * traffic volume, see the roadmap's open GPCS questions.
 */
final class PunchoutThrottle
{
    private const MAX_ATTEMPTS = 30;

    private const DECAY_SECONDS = 60;

    public function handle(Request $request, Closure $next, string $limiterName): Response
    {
        $key = $this->resolveKey($request, $limiterName);

        if (RateLimiter::tooManyAttempts($key, self::MAX_ATTEMPTS)) {
            return $this->tooManyAttemptsResponse(RateLimiter::availableIn($key));
        }

        RateLimiter::hit($key, self::DECAY_SECONDS);

        return $next($request);
    }

    private function resolveKey(Request $request, string $limiterName): string
    {
        $fromIdentity = $this->extractFromIdentity((string) $request->getContent());

        // A genuinely unparseable body falls back to IP: it cannot be
        // attributed to a specific buyer identity, but it still needs
        // some key to limit on rather than bypassing throttling entirely.
        return $fromIdentity !== null
            ? "punchout-throttle:{$limiterName}:from:{$fromIdentity}"
            : "punchout-throttle:{$limiterName}:ip:{$request->ip()}";
    }

    private function extractFromIdentity(string $rawXml): ?string
    {
        try {
            $reader = new XPathReader(XmlSecurity::loadSafely($rawXml));

            return $reader->text('/cXML/Header/From/Credential/Identity');
        } catch (Throwable) {
            return null;
        }
    }

    private function tooManyAttemptsResponse(int $retryAfterSeconds): Response
    {
        $xml = '<?xml version="1.0" encoding="UTF-8"?>'
            .'<!DOCTYPE cXML SYSTEM "http://xml.cxml.org/schemas/cXML/1.2.014/cXML.dtd">'
            .'<cXML><Response><Status code="429" text="Too Many Requests"/></Response></cXML>';

        return response($xml, 429)
            ->header('Content-Type', 'text/xml; charset=UTF-8')
            ->header('Retry-After', (string) $retryAfterSeconds);
    }
}
