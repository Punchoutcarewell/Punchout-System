<?php

declare(strict_types=1);

namespace App\Modules\Punchout\Http\Middleware;

use App\Modules\Punchout\Enums\PunchoutMessageType;
use App\Modules\Punchout\Services\PunchoutLogger;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Routing\Router;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

/**
 * Guarantees a cXML request never gets an HTML answer, whatever URL it was
 * sent to.
 *
 * A buyer system is configured with a single PunchOut URL and a single PO
 * URL. When one of those points at a browser page rather than the real
 * endpoints (/storefront, /, an admin page), the buyer's XML parser is
 * handed an HTML document and fails with an error that says nothing about
 * what went wrong (a raw "&" in a font URL or in CSS, for example).
 *
 * For any request that carries a cXML body and is not already addressed
 * to /api/punchout/setup or /api/punchout/order:
 *
 * - a PunchOutSetupRequest or OrderRequest is forwarded internally to the
 *   real endpoint, so a misconfigured URL still works. The real route's
 *   own middleware (throttling) and controller (credential validation,
 *   logging, cXML responses) all still run, nothing is bypassed.
 * - anything else gets a well-formed cXML fault naming the correct URLs.
 *
 * Both cases are written to the punchout log channel, and the second to
 * punchout_logs, so a misdirected request is visible instead of leaving no
 * trace at all.
 *
 * Browser traffic is unaffected: it carries no XML body, so this passes
 * straight through to the router.
 */
final class RouteMisdirectedCxml
{
    private const SETUP_PATH = 'api/punchout/setup';

    private const ORDER_PATH = 'api/punchout/order';

    /** Only the start of the body is inspected, a cXML root and its first Request element sit well inside this. */
    private const SNIFF_BYTES = 65536;

    private const MAX_FAULTS_PER_MINUTE = 30;

    public function __construct(
        private readonly Router $router,
        private readonly PunchoutLogger $logger,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        if (! $this->isCxmlRequest($request)) {
            return $next($request);
        }

        $path = trim($request->path(), '/');

        if ($path === self::SETUP_PATH || $path === self::ORDER_PATH) {
            return $next($request);
        }

        $body = (string) $request->getContent();
        $target = $this->targetFor($body);

        if ($target !== null) {
            Log::channel('punchout')->warning('cXML request sent to the wrong URL, forwarding to the real endpoint.', [
                'received_at' => $request->method().' /'.$path,
                'forwarded_to' => '/'.$target,
            ]);

            return $this->router->dispatch($this->forwardedRequest($request, $target, $body));
        }

        return $this->fault($request, $path, $body);
    }

    private function isCxmlRequest(Request $request): bool
    {
        if (! in_array($request->method(), ['POST', 'PUT', 'PATCH'], true)) {
            return false;
        }

        if (str_contains(strtolower((string) $request->header('Content-Type')), 'xml')) {
            return true;
        }

        return (bool) preg_match('/^\s*(<\?xml|<!DOCTYPE\s+cXML|<cXML)/i', substr((string) $request->getContent(), 0, 200));
    }

    private function targetFor(string $body): ?string
    {
        $head = substr($body, 0, self::SNIFF_BYTES);

        if (preg_match('/<(?:[\w.\-]+:)?PunchOutSetupRequest[\s>\/]/i', $head) === 1) {
            return self::SETUP_PATH;
        }

        if (preg_match('/<(?:[\w.\-]+:)?OrderRequest[\s>\/]/i', $head) === 1) {
            return self::ORDER_PATH;
        }

        return null;
    }

    private function forwardedRequest(Request $original, string $target, string $body): Request
    {
        $server = array_merge($original->server->all(), [
            'REQUEST_METHOD' => 'POST',
            'REQUEST_URI' => '/'.$target,
            'PATH_INFO' => '/'.$target,
        ]);

        return Request::create('/'.$target, 'POST', [], $original->cookies->all(), [], $server, $body);
    }

    private function fault(Request $request, string $path, string $body): Response
    {
        $detail = 'Unrecognised cXML request. Send a PunchOutSetupRequest to /api/punchout/setup and an OrderRequest to /api/punchout/order.';

        // A flood of junk XML aimed at random URLs must not be able to
        // fill punchout_logs: past the limit the fault is still returned,
        // it just is not recorded.
        $key = 'cxml-misdirected:'.$request->ip();

        if (RateLimiter::tooManyAttempts($key, self::MAX_FAULTS_PER_MINUTE)) {
            return $this->xml(429, 'Too many requests.');
        }

        RateLimiter::hit($key, 60);

        try {
            $this->logger->logInbound(
                PunchoutMessageType::Unrecognised,
                $body,
                httpStatus: 400,
                error: "Received at {$request->method()} /{$path}: not a PunchOutSetupRequest or OrderRequest.",
            );
        } catch (Throwable) {
            // A failed log write must never turn this into an HTML error.
        }

        return $this->xml(400, $detail);
    }

    private function xml(int $code, string $text): Response
    {
        $text = htmlspecialchars($text, ENT_XML1 | ENT_QUOTES, 'UTF-8');

        $xml = '<?xml version="1.0" encoding="UTF-8"?>'
            .'<!DOCTYPE cXML SYSTEM "http://xml.cxml.org/schemas/cXML/1.2.014/cXML.dtd">'
            ."<cXML><Response><Status code=\"{$code}\" text=\"{$text}\"/></Response></cXML>";

        return response($xml, $code)->header('Content-Type', 'text/xml; charset=UTF-8');
    }
}
