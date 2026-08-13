<?php

declare(strict_types=1);

it('sets baseline security headers on a normal web response', function () {
    $response = $this->get('/storefront');

    expect($response->headers->get('X-Content-Type-Options'))->toBe('nosniff')
        ->and($response->headers->get('Referrer-Policy'))->toBe('strict-origin-when-cross-origin');
});

it('sets baseline security headers on a cXML response, which runs outside the web middleware group', function () {
    $response = $this->call('POST', '/api/punchout/setup', content: '<not-xml', server: ['CONTENT_TYPE' => 'text/xml']);

    expect($response->headers->get('X-Content-Type-Options'))->toBe('nosniff')
        ->and($response->headers->get('Referrer-Policy'))->toBe('strict-origin-when-cross-origin');
});

it('never sets X-Frame-Options, since that would conflict with this app being embedded in Coupa\'s iframe', function () {
    $response = $this->get('/storefront');

    expect($response->headers->has('X-Frame-Options'))->toBeFalse();
});
