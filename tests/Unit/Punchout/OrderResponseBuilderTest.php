<?php

declare(strict_types=1);

use App\Modules\Punchout\Cxml\OrderResponseBuilder;
use App\Modules\Punchout\Data\OrderResponseData;

it('builds an acknowledgement response', function () {
    $xml = (new OrderResponseBuilder)->build(OrderResponseData::accepted());

    expect($xml)->toContain('code="200"')
        ->and($xml)->toContain('text="OK"');
});

it('builds a failure response with the given status', function () {
    $xml = (new OrderResponseBuilder)->build(new OrderResponseData(400, 'Malformed request.'));

    expect($xml)->toContain('code="400"')
        ->and($xml)->toContain('text="Malformed request."');
});
