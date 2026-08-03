<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * The master UNSPSC code list: one row per 8-digit code with its
 * human-readable segment/family/class/commodity titles, sourced from
 * unspsc.org. Purely a reference/lookup table for showing readable labels
 * in the admin and storefront, populating it is a separate, largely
 * non-engineering workstream (see the roadmap's UNSPSC mapping risk).
 *
 * products.unspsc_code is deliberately NOT a foreign key into this table:
 * requiring every product insert to wait on the full ~50,000-code
 * taxonomy being loaded first would block ordinary development. Validity
 * is enforced at the application layer instead, via the Shared
 * UnspscCode value object and the catalog:validate command.
 *
 * Named UnspscReference rather than UnspscCode, even though the original
 * architecture doc used the latter, specifically to avoid two classes
 * named UnspscCode in the same codebase: this is a database-backed
 * reference row, App\Shared\ValueObjects\UnspscCode is the validated
 * value type used everywhere else.
 *
 * @property string $code
 * @property string $segment
 * @property string $family
 * @property string $class
 * @property string $commodity
 * @property string $title
 */
final class UnspscReference extends Model
{
    protected $table = 'unspsc_references';

    protected $primaryKey = 'code';

    public $incrementing = false;

    protected $keyType = 'string';

    public $timestamps = false;

    protected $fillable = ['code', 'segment', 'family', 'class', 'commodity', 'title'];
}
