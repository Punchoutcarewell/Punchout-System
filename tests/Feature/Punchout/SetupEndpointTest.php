<?php

declare(strict_types=1);

use App\Modules\Punchout\Contracts\PunchoutProtocolInterface;
use App\Modules\Punchout\Models\PunchoutLog;
use App\Modules\Punchout\Models\PunchoutSession;

it('accepts a valid setup request and creates a session', function () {
    createTestPunchoutCredential('ALD');

    $xml = (string) file_get_contents(base_path('tests/Fixtures/Cxml/setup_request.xml'));

    $response = $this->call('POST', '/api/punchout/setup', content: $xml, server: ['CONTENT_TYPE' => 'text/xml']);

    $response->assertStatus(200);
    expect($response->getContent())->toContain('code="200"')
        ->and($response->getContent())->toContain('<StartPage>')
        ->and($response->headers->get('Content-Type'))->toContain('text/xml');

    expect(PunchoutSession::query()->count())->toBe(1);

    $session = PunchoutSession::query()->first();
    expect($session->buyer_cookie)->toBe('99ea3c4c8cf9f6dc905a6b6772daa0d1')
        ->and($session->buyer_user_email)->toBe('maryanne.krzeminski@coupa.com')
        ->and($session->buyer_business_unit)->toBe('COUPA')
        ->and($session->buyer_first_name)->toBe('Mary Anne')
        ->and($session->buyer_last_name)->toBe('Krzeminski')
        ->and($session->contact_name)->toBe('maryanne.krzeminski@coupa.com')
        ->and($session->contact_email)->toBe('maryanne.krzeminski@coupa.com')
        ->and($session->supplier_setup_url)->toBe('https://uttest.free.beeceptor.com');
});

it('honours X-Forwarded-Proto so the StartPage URL is https behind a proxy', function () {
    createTestPunchoutCredential('ALD');

    $xml = (string) file_get_contents(base_path('tests/Fixtures/Cxml/setup_request.xml'));

    $response = $this->call('POST', '/api/punchout/setup', content: $xml, server: [
        'CONTENT_TYPE' => 'text/xml',
        'HTTP_X_FORWARDED_PROTO' => 'https',
        'HTTP_X_FORWARDED_FOR' => '203.0.113.7',
    ]);

    $response->assertStatus(200);
    expect($response->getContent())->toMatch('#<URL>https://#');
});

it('rejects a request with the wrong shared secret and creates no session', function () {
    createTestPunchoutCredential('THE-REAL-SECRET');

    $xml = (string) file_get_contents(base_path('tests/Fixtures/Cxml/setup_request.xml')); // carries SharedSecret "ALD"

    $response = $this->call('POST', '/api/punchout/setup', content: $xml, server: ['CONTENT_TYPE' => 'text/xml']);

    $response->assertStatus(401);
    expect($response->getContent())->toContain('code="401"');
    expect(PunchoutSession::query()->count())->toBe(0);
});

it('rejects malformed XML with a valid cXML error response, not an HTML error page', function () {
    $response = $this->call('POST', '/api/punchout/setup', content: '<not-xml', server: ['CONTENT_TYPE' => 'text/xml']);

    $response->assertStatus(400);
    expect($response->getContent())->toContain('<?xml')
        ->and($response->getContent())->toContain('code="400"')
        ->and($response->getContent())->not->toContain('<html');
});

it('still returns a well-formed cXML error, never a bare 500, when something entirely unexpected fails', function () {
    $this->mock(PunchoutProtocolInterface::class, function ($mock): void {
        $mock->shouldReceive('parseSetupRequest')->andThrow(new RuntimeException('unexpected native failure'));
    });

    $xml = (string) file_get_contents(base_path('tests/Fixtures/Cxml/setup_request.xml'));

    $response = $this->call('POST', '/api/punchout/setup', content: $xml, server: ['CONTENT_TYPE' => 'text/xml']);

    $response->assertStatus(500);
    expect($response->getContent())->toContain('<?xml')
        ->and($response->getContent())->toContain('code="500"')
        ->and($response->getContent())->not->toContain('<html');
});

it('logs the raw payload with the shared secret redacted', function () {
    createTestPunchoutCredential('ALD');

    $xml = (string) file_get_contents(base_path('tests/Fixtures/Cxml/setup_request.xml'));

    $this->call('POST', '/api/punchout/setup', content: $xml, server: ['CONTENT_TYPE' => 'text/xml']);

    $log = PunchoutLog::query()->where('message_type', 'setup_request')->first();

    expect($log)->not->toBeNull()
        ->and($log->raw_payload)->toContain('[REDACTED]')
        ->and($log->raw_payload)->not->toContain('ALD');
});
