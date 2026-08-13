<?php

use App\Models\User;
use App\Modules\Catalog\Models\Category;
use App\Modules\Catalog\Models\Product;
use App\Modules\Punchout\Data\OrderRequestData;
use App\Modules\Punchout\Data\OrderRequestLineData;
use App\Modules\Punchout\Enums\PunchoutEnvironment;
use App\Modules\Punchout\Models\PunchoutCredential;
use App\Modules\Punchout\Models\PunchoutSession;
use App\Shared\ValueObjects\Money;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a specific PHPUnit test
| case class. By default, that class is "PHPUnit\Framework\TestCase". Of course, you may
| need to change it using the "pest()" function to bind a different classes or traits.
|
*/

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature');

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
|
| When you're writing tests, you often need to check that values meet certain conditions. The
| "expect()" function gives you access to a set of "expectations" methods that you can use
| to assert different things. Of course, you may extend the Expectation API at any time.
|
*/

expect()->extend('toBeOne', function () {
    return $this->toBe(1);
});

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
|
| While Pest is very powerful out-of-the-box, you may have some testing code specific to your
| project that you don't want to repeat in every file. Here you can also expose helpers as
| global functions to help you to reduce the number of lines of code in your test files.
|
*/

function createTestPunchoutCredential(string $sharedSecret = 'ALD'): PunchoutCredential
{
    return PunchoutCredential::query()->create([
        'environment' => PunchoutEnvironment::Test,
        'from_domain' => 'DUNS',
        'from_identity' => 'COUPA1',
        'to_domain' => 'DUNS',
        'to_identity' => '079928354',
        'shared_secret' => $sharedSecret,
        'sender_domain' => 'DUNS',
        'sender_identity' => 'COUPA1',
        'protocol' => 'cxml',
        'is_active' => true,
    ]);
}

function createTestCategory(string $name = 'Wound Care'): Category
{
    return Category::query()->create([
        'name' => $name,
        'slug' => Str::slug($name),
        'position' => 0,
    ]);
}

/**
 * Runs a real /api/punchout/setup request to get a genuinely bound
 * PunchoutSession, the same round trip a browser would go through, rather
 * than inserting a session row by hand.
 */
function issueTestPunchoutSession(): PunchoutSession
{
    createTestPunchoutCredential('ALD');

    $xml = (string) file_get_contents(dirname(__DIR__).'/tests/Fixtures/Cxml/setup_request.xml');

    test()->call('POST', '/api/punchout/setup', content: $xml, server: ['CONTENT_TYPE' => 'text/xml']);

    return PunchoutSession::query()->firstOrFail();
}

function actingAsAdmin(): User
{
    $user = User::factory()->create();

    test()->actingAs($user);

    return $user;
}

function sampleOrderRequestData(string $poNumber = 'PO-1'): OrderRequestData
{
    return new OrderRequestData(
        fromDomain: 'DUNS',
        fromIdentity: 'COUPA1',
        toDomain: 'DUNS',
        toIdentity: '079928354',
        senderDomain: 'DUNS',
        senderIdentity: 'COUPA1',
        sharedSecret: 'ALD',
        poNumber: $poNumber,
        orderDate: new DateTimeImmutable('2026-08-10T10:00:00-05:00'),
        total: Money::fromDecimal('25.99', 'AUD'),
        buyerReference: 'REQ-123',
        buyerCookie: null,
        lines: [
            new OrderRequestLineData(
                lineNumber: 1,
                supplierPartId: 'CW-4021',
                quantity: 1,
                unitPrice: Money::fromDecimal('25.99', 'AUD'),
                unitOfMeasure: 'BX',
                description: 'Foam Wound Dressing',
            ),
        ],
    );
}

/**
 * @param  array<string, mixed>  $overrides
 */
function createTestProduct(array $overrides = []): Product
{
    return Product::query()->create(array_merge([
        'sku' => 'CW-'.random_int(100000, 999999),
        'supplier_part_id' => 'CW-4021',
        'name' => 'Foam Wound Dressing 10cm, Pack of 10',
        'description' => 'Foam Wound Dressing 10cm, Pack of 10',
        'unspsc_code' => '42311505',
        'unit_of_measure' => 'BX',
        'pack_size' => 10,
        'lead_time_days' => 2,
        'list_price' => '29.99',
        'currency' => 'AUD',
        'is_active' => true,
    ], $overrides));
}
