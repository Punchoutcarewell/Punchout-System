<?php

declare(strict_types=1);

use App\Modules\Punchout\Cxml\OrderMessageBuilder;
use App\Modules\Punchout\Data\CartLineSnapshot;
use App\Modules\Punchout\Data\CartSnapshot;
use App\Modules\Punchout\Data\OrderMessageData;
use App\Shared\ValueObjects\Money;
use App\Shared\ValueObjects\UnspscCode;

it('echoes the buyer cookie verbatim and renders every item field', function () {
    $cart = new CartSnapshot(
        lines: [
            new CartLineSnapshot(
                supplierPartId: 'CW-4021',
                supplierPartAuxiliaryId: 'PK10',
                quantity: 2,
                unitPrice: Money::fromDecimal('25.99', 'AUD'),
                description: 'Foam Wound Dressing 10cm, Pack of 10',
                unitOfMeasure: 'BX',
                unspscCode: UnspscCode::fromString('42311505'),
                manufacturerPartId: 'CW-4021',
                manufacturerName: 'Carewell',
                leadTimeDays: 2,
            ),
        ],
        currency: 'AUD',
    );

    $data = new OrderMessageData(
        fromDomain: 'DUNS',
        fromIdentity: '079928354',
        toDomain: 'DUNS',
        toIdentity: 'COUPA1',
        senderDomain: 'DUNS',
        senderIdentity: '079928354',
        buyerCookie: '99ea3c4c8cf9f6dc905a6b6772daa0d1',
        cart: $cart,
        deploymentMode: 'test',
    );

    $xml = (new OrderMessageBuilder)->build($data);

    expect($xml)->toContain('<!DOCTYPE cXML SYSTEM "http://xml.cxml.org/schemas/cXML/1.2.014/cXML.dtd">')
        ->and($xml)->toContain('deploymentMode="test"')
        ->and($xml)->toContain('<BuyerCookie>99ea3c4c8cf9f6dc905a6b6772daa0d1</BuyerCookie>')
        ->and($xml)->toContain('ItemIn quantity="2"')
        ->and($xml)->toContain('<SupplierPartID>CW-4021</SupplierPartID>')
        ->and($xml)->toContain('<SupplierPartAuxiliaryID>PK10</SupplierPartAuxiliaryID>')
        ->and($xml)->toContain('<Money currency="AUD">25.99</Money>')
        ->and($xml)->toContain('<UnitOfMeasure>BX</UnitOfMeasure>')
        ->and($xml)->toContain('domain="UNSPSC">42311505</Classification>')
        ->and($xml)->toContain('<ManufacturerPartID>CW-4021</ManufacturerPartID>')
        ->and($xml)->toContain('<LeadTime>2</LeadTime>');
});

it('sums multiple lines into the PunchOutOrderMessageHeader total', function () {
    $cart = new CartSnapshot(
        lines: [
            new CartLineSnapshot('SKU-A', null, 2, Money::fromDecimal('10.00', 'AUD'), 'Item A', 'EA', UnspscCode::fromString('42311505'), null, null, 1),
            new CartLineSnapshot('SKU-B', null, 1, Money::fromDecimal('5.50', 'AUD'), 'Item B', 'EA', UnspscCode::fromString('42311505'), null, null, 1),
        ],
        currency: 'AUD',
    );

    $data = new OrderMessageData(
        fromDomain: 'DUNS',
        fromIdentity: '079928354',
        toDomain: 'DUNS',
        toIdentity: 'COUPA1',
        senderDomain: 'DUNS',
        senderIdentity: '079928354',
        buyerCookie: 'cookie123',
        cart: $cart,
    );

    $xml = (new OrderMessageBuilder)->build($data);

    expect($xml)->toContain('<Total><Money currency="AUD">25.50</Money></Total>');
});

it('omits SupplierPartAuxiliaryID when the line has none', function () {
    $cart = new CartSnapshot(
        lines: [
            new CartLineSnapshot('SKU-A', null, 1, Money::fromDecimal('10.00', 'AUD'), 'Item A', 'EA', UnspscCode::fromString('42311505'), null, null, 1),
        ],
        currency: 'AUD',
    );

    $data = new OrderMessageData(
        fromDomain: 'DUNS',
        fromIdentity: '079928354',
        toDomain: 'DUNS',
        toIdentity: 'COUPA1',
        senderDomain: 'DUNS',
        senderIdentity: '079928354',
        buyerCookie: 'cookie123',
        cart: $cart,
    );

    $xml = (new OrderMessageBuilder)->build($data);

    expect($xml)->not->toContain('SupplierPartAuxiliaryID');
});
