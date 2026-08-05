<?php

declare(strict_types=1);

use App\Modules\Punchout\Cxml\CxmlDocumentFactory;

it('generates a UUID-based payloadId, not a time-based uniqid', function () {
    $payloadId = (new CxmlDocumentFactory)->payloadId();

    expect($payloadId)->toEndWith('@carewellgroup.com.au')
        ->and($payloadId)->toMatch('/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}@carewellgroup\.com\.au$/');
});

it('generates a different payloadId on every call', function () {
    $factory = new CxmlDocumentFactory;

    expect($factory->payloadId())->not->toBe($factory->payloadId());
});
