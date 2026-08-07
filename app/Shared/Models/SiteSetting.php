<?php

declare(strict_types=1);

namespace App\Shared\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * A single-row settings table (see the migration for why this is not a
 * key/value scheme): the branding this application-wide, not per-module,
 * so it lives in Shared rather than Admin or Storefront, the two modules
 * that actually read and write it.
 *
 * @property int $id
 * @property string|null $logo_path relative to the "public" disk, see ProductResource's image_path for the same convention
 */
final class SiteSetting extends Model
{
    protected $table = 'site_settings';

    protected $fillable = ['logo_path'];

    /**
     * The one row this table ever holds, created on first access rather
     * than seeded, so a fresh install needs no seeder just to make the
     * Admin settings page work.
     */
    public static function current(): self
    {
        return self::query()->firstOrCreate(['id' => 1]);
    }
}
