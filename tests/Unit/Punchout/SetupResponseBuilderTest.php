<?php

declare(strict_types=1);

use App\Modules\Punchout\Cxml\SetupResponseBuilder;
use App\Modules\Punchout\Data\SetupResponseData;

it('builds a success response containing the start URL', function () {
    $xml = (new SetupResponseBuilder)->build(
        SetupResponseData::success('https://carewellgroup.com.au/punchout/start?token=abc123'),
    );

    expect($xml)->toContain('<!DOCTYPE cXML SYSTEM "http://xml.cxml.org/schemas/cXML/1.2.014/cXML.dtd">')
        ->and($xml)->toContain('code="200"')
        ->and($xml)->toContain('text="OK"')
        ->and($xml)->toContain('<StartPage>')
        ->and($xml)->toContain('https://carewellgroup.com.au/punchout/start?token=abc123');
});

it('builds a failure response with no StartPage element', function () {
    $xml = (new SetupResponseBuilder)->build(SetupResponseData::failure(401, 'Unauthorized.'));

    expect($xml)->toContain('code="401"')
        ->and($xml)->toContain('text="Unauthorized."')
        ->and($xml)->not->toContain('StartPage');
});
