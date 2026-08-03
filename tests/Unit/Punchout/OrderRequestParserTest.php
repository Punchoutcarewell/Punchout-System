<?php

declare(strict_types=1);

use App\Modules\Punchout\Cxml\OrderRequestParser;
use App\Modules\Punchout\Exceptions\MalformedCxmlException;

/**
 * No sample OrderRequest payload was provided in Amazon's supplier
 * questionnaire or the GPCS process guide, PO transmission type (CSP,
 * email, or cXML) is still an open question for this project. This
 * fixture follows the standard cXML OrderRequest structure and needs
 * validation against a real Coupa-issued specimen once that question is
 * answered, see the docblock on OrderRequestParser itself.
 */
function orderRequestFixtureXml(): string
{
    return <<<'XML'
    <?xml version="1.0" encoding="UTF-8"?>
    <!DOCTYPE cXML SYSTEM "http://xml.cxml.org/schemas/cXML/1.2.014/cXML.dtd">
    <cXML xml:lang="en-US" payloadID="1@coupahost.com" timestamp="2026-08-10T10:00:00-05:00">
      <Header>
        <From><Credential domain="DUNS"><Identity>COUPA1</Identity></Credential></From>
        <To><Credential domain="DUNS"><Identity>079928354</Identity></Credential></To>
        <Sender><Credential domain="DUNS"><Identity>COUPA1</Identity></Credential></Sender>
      </Header>
      <Request deploymentMode="production">
        <OrderRequest>
          <OrderRequestHeader orderID="PO-98765" orderDate="2026-08-10T10:00:00-05:00" type="new">
            <Total><Money currency="AUD">271.88</Money></Total>
            <Extrinsic name="buyerReference">REQ-4471</Extrinsic>
          </OrderRequestHeader>
          <ItemOut lineNumber="1" quantity="2">
            <ItemID><SupplierPartID>CW-4021</SupplierPartID></ItemID>
            <ItemDetail>
              <UnitPrice><Money currency="AUD">25.99</Money></UnitPrice>
              <Description xml:lang="en-US">Foam Wound Dressing 10cm, Pack of 10</Description>
              <UnitOfMeasure>BX</UnitOfMeasure>
            </ItemDetail>
          </ItemOut>
          <ItemOut lineNumber="2" quantity="1">
            <ItemID><SupplierPartID>CW-8890</SupplierPartID></ItemID>
            <ItemDetail>
              <UnitPrice><Money currency="AUD">219.90</Money></UnitPrice>
              <Description xml:lang="en-US">Standard Wheelchair, Folding Frame</Description>
              <UnitOfMeasure>EA</UnitOfMeasure>
            </ItemDetail>
          </ItemOut>
        </OrderRequest>
      </Request>
    </cXML>
    XML;
}

it('parses the PO header and every line', function () {
    $data = (new OrderRequestParser)->parse(orderRequestFixtureXml());

    expect($data->poNumber)->toBe('PO-98765')
        ->and($data->total->toDecimalString())->toBe('271.88')
        ->and($data->buyerReference)->toBe('REQ-4471')
        ->and($data->lines)->toHaveCount(2);

    expect($data->lines[0]->supplierPartId)->toBe('CW-4021')
        ->and($data->lines[0]->quantity)->toBe(2)
        ->and($data->lines[0]->unitPrice->toDecimalString())->toBe('25.99')
        ->and($data->lines[0]->unitOfMeasure)->toBe('BX');

    expect($data->lines[1]->supplierPartId)->toBe('CW-8890')
        ->and($data->lines[1]->quantity)->toBe(1);
});

it('rejects an OrderRequest missing the orderID attribute', function () {
    $xml = str_replace('orderID="PO-98765"', '', orderRequestFixtureXml());

    (new OrderRequestParser)->parse($xml);
})->throws(MalformedCxmlException::class);

it('rejects an OrderRequest with no line items', function () {
    $xml = preg_replace('/<ItemOut.*?<\/ItemOut>/s', '', orderRequestFixtureXml());

    (new OrderRequestParser)->parse($xml);
})->throws(MalformedCxmlException::class);
