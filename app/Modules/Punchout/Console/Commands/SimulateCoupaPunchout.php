<?php

declare(strict_types=1);

namespace App\Modules\Punchout\Console\Commands;

use App\Modules\Punchout\Enums\PunchoutEnvironment;
use App\Modules\Punchout\Http\Middleware\ResolvePunchoutSession;
use App\Modules\Punchout\Models\PunchoutCredential;
use DateTimeImmutable;
use Illuminate\Console\Command;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

/**
 * Exercises the PunchOut protocol layer against this application's own
 * endpoints, the discipline the roadmap's Stage 2 calls for: every defect
 * found against a simulator costs seconds, the same defect found against
 * Coupa costs a coordination cycle with a third party.
 *
 * Runs two round trips independently, since the Cart/Storefront modules
 * that would join them into a single cart-transfer flow do not exist yet:
 *
 *   1. Setup -> Start: proves credential validation, session creation,
 *      and the cookie/redirect mechanics all work end to end.
 *   2. OrderRequest -> Response: proves the PO-receiving endpoint parses
 *      and acknowledges correctly, independent of how the PO transmission
 *      question (CSP, email, or cXML) is eventually answered.
 *
 * Uses a dedicated "simulator" credential row, upserted on every run and
 * kept entirely separate from any real Coupa credential.
 */
final class SimulateCoupaPunchout extends Command
{
    protected $signature = 'punchout:simulate {--base-url= : Override the base URL, defaults to config(app.url)}';

    protected $description = "Simulate a Coupa PunchOut round trip against this application's own endpoints.";

    private const SHARED_SECRET = 'simulator-shared-secret';

    public function handle(): int
    {
        $baseUrl = rtrim((string) ($this->option('base-url') ?: config('app.url')), '/');

        $this->components->info("Simulating against {$baseUrl}");

        $this->ensureSimulatorCredential();

        if (! $this->simulateSetupAndStart($baseUrl)) {
            return self::FAILURE;
        }

        if (! $this->simulateOrderRequest($baseUrl)) {
            return self::FAILURE;
        }

        $this->components->info('Simulation complete: setup, start, and order-request round trips all succeeded.');

        return self::SUCCESS;
    }

    private function ensureSimulatorCredential(): void
    {
        PunchoutCredential::query()->updateOrCreate(
            [
                'environment' => PunchoutEnvironment::Test,
                'to_domain' => 'DUNS',
                'to_identity' => 'CAREWELL-SIM',
            ],
            [
                'shared_secret' => self::SHARED_SECRET,
                'sender_domain' => 'DUNS',
                'sender_identity' => 'COUPA-SIM',
                'protocol' => 'cxml',
                'is_active' => true,
            ],
        );
    }

    private function simulateSetupAndStart(string $baseUrl): bool
    {
        $buyerCookie = Str::random(32);
        $setupXml = $this->buildSetupRequestXml($buyerCookie);
        $setupResponse = null;

        $this->components->task('POST /punchout/setup', function () use (&$setupResponse, $baseUrl, $setupXml): bool {
            $setupResponse = Http::withBody($setupXml, 'text/xml')->post("{$baseUrl}/punchout/setup");

            return $setupResponse->successful();
        });

        if (! $setupResponse instanceof Response || ! $setupResponse->successful()) {
            $this->components->error('Setup request failed: '.($setupResponse?->body() ?? 'no response'));

            return false;
        }

        if (! preg_match('/<URL>(.*?)<\/URL>/', $setupResponse->body(), $matches)) {
            $this->components->error('PunchOutSetupResponse did not contain a StartPage URL.');
            $this->line($setupResponse->body());

            return false;
        }

        $startUrl = html_entity_decode($matches[1]);
        $startResponse = null;

        $this->components->task('GET '.parse_url($startUrl, PHP_URL_PATH), function () use (&$startResponse, $startUrl): bool {
            $startResponse = Http::withOptions(['allow_redirects' => false])->get($startUrl);

            return in_array($startResponse->status(), [301, 302, 303, 307, 308], true);
        });

        if (! $startResponse instanceof Response || ! in_array($startResponse->status(), [301, 302, 303, 307, 308], true)) {
            $this->components->error('The start URL did not redirect as expected.');

            return false;
        }

        $this->components->twoColumnDetail(
            'Session cookie set',
            $this->responseHasCookie($startResponse, ResolvePunchoutSession::COOKIE_NAME) ? 'yes' : 'no',
        );

        return true;
    }

    private function simulateOrderRequest(string $baseUrl): bool
    {
        $orderXml = $this->buildOrderRequestXml();
        $orderResponse = null;

        $this->components->task('POST /punchout/order', function () use (&$orderResponse, $baseUrl, $orderXml): bool {
            $orderResponse = Http::withBody($orderXml, 'text/xml')->post("{$baseUrl}/punchout/order");

            return $orderResponse->successful();
        });

        if (! $orderResponse instanceof Response || ! $orderResponse->successful()) {
            $this->components->error('OrderRequest failed: '.($orderResponse?->body() ?? 'no response'));

            return false;
        }

        return str_contains($orderResponse->body(), 'code="200"');
    }

    private function responseHasCookie(Response $response, string $name): bool
    {
        foreach ($response->cookies() as $cookie) {
            if ($cookie->getName() === $name) {
                return true;
            }
        }

        return false;
    }

    private function buildSetupRequestXml(string $buyerCookie): string
    {
        $payloadId = $this->payloadId();
        $timestamp = $this->timestamp();
        $sharedSecret = self::SHARED_SECRET;
        $browserFormPostUrl = 'https://simulator.invalid/cart/transfer?id='.Str::random(6);

        return <<<XML
        <?xml version="1.0" encoding="UTF-8"?>
        <!DOCTYPE cXML SYSTEM "http://xml.cxml.org/schemas/cXML/1.2.014/cXML.dtd">
        <cXML xml:lang="en-US" payloadID="{$payloadId}" timestamp="{$timestamp}">
          <Header>
            <From><Credential domain="DUNS"><Identity>COUPA-SIM</Identity></Credential></From>
            <To><Credential domain="DUNS"><Identity>CAREWELL-SIM</Identity></Credential></To>
            <Sender>
              <Credential domain="DUNS"><Identity>COUPA-SIM</Identity><SharedSecret>{$sharedSecret}</SharedSecret></Credential>
              <UserAgent>Punchout Simulator 1.0</UserAgent>
            </Sender>
          </Header>
          <Request>
            <PunchOutSetupRequest operation="create">
              <BuyerCookie>{$buyerCookie}</BuyerCookie>
              <Extrinsic name="UserEmail">simulator@example.com</Extrinsic>
              <Extrinsic name="UniqueName">simulator@example.com</Extrinsic>
              <Extrinsic name="BusinessUnit">SIMULATOR</Extrinsic>
              <BrowserFormPost><URL>{$browserFormPostUrl}</URL></BrowserFormPost>
              <Contact role="endUser">
                <Name xml:lang="en-US">Simulator</Name>
                <Email>simulator@example.com</Email>
              </Contact>
            </PunchOutSetupRequest>
          </Request>
        </cXML>
        XML;
    }

    private function buildOrderRequestXml(): string
    {
        $payloadId = $this->payloadId();
        $timestamp = $this->timestamp();

        return <<<XML
        <?xml version="1.0" encoding="UTF-8"?>
        <!DOCTYPE cXML SYSTEM "http://xml.cxml.org/schemas/cXML/1.2.014/cXML.dtd">
        <cXML xml:lang="en-US" payloadID="{$payloadId}" timestamp="{$timestamp}">
          <Header>
            <From><Credential domain="DUNS"><Identity>COUPA-SIM</Identity></Credential></From>
            <To><Credential domain="DUNS"><Identity>CAREWELL-SIM</Identity></Credential></To>
            <Sender><Credential domain="DUNS"><Identity>COUPA-SIM</Identity></Credential></Sender>
          </Header>
          <Request deploymentMode="test">
            <OrderRequest>
              <OrderRequestHeader orderID="SIM-PO-1" orderDate="{$timestamp}" type="new">
                <Total><Money currency="AUD">51.98</Money></Total>
                <Extrinsic name="buyerReference">SIM-REQ-1</Extrinsic>
              </OrderRequestHeader>
              <ItemOut lineNumber="1" quantity="2">
                <ItemID><SupplierPartID>CW-4021</SupplierPartID></ItemID>
                <ItemDetail>
                  <UnitPrice><Money currency="AUD">25.99</Money></UnitPrice>
                  <Description xml:lang="en-US">Foam Wound Dressing 10cm, Pack of 10</Description>
                  <UnitOfMeasure>BX</UnitOfMeasure>
                </ItemDetail>
              </ItemOut>
            </OrderRequest>
          </Request>
        </cXML>
        XML;
    }

    private function payloadId(): string
    {
        return uniqid('', true).'@simulator.local';
    }

    private function timestamp(): string
    {
        return (new DateTimeImmutable)->format(DATE_ATOM);
    }
}
