<?php

declare(strict_types=1);

namespace App\Modules\Punchout\Models;

use App\Modules\Punchout\Enums\PunchoutEnvironment;
use Illuminate\Database\Eloquent\Model;

/**
 * One row per environment (test, production). shared_secret is encrypted
 * at rest via Laravel's own encrypter (APP_KEY), never stored or logged in
 * plain text.
 *
 * Managed through the Admin module's Filament resource once that module
 * exists, there is deliberately no other way to create or edit a row, so
 * credentials never live in a migration, seeder, or committed .env file.
 * This is also the model that makes onboarding a config event rather than
 * a deploy: once Coupa issues test credentials, an admin fills this row in
 * and the same code path that runs against the simulator starts
 * authenticating real requests, no code change required.
 *
 * @property int $id
 * @property PunchoutEnvironment $environment
 * @property string $to_domain
 * @property string $to_identity
 * @property string $shared_secret
 * @property string $sender_domain
 * @property string $sender_identity
 * @property string $protocol
 * @property bool $is_active
 */
final class PunchoutCredential extends Model
{
    protected $table = 'punchout_credentials';

    protected $fillable = [
        'environment',
        'to_domain',
        'to_identity',
        'shared_secret',
        'sender_domain',
        'sender_identity',
        'protocol',
        'is_active',
    ];

    protected $hidden = [
        'shared_secret',
    ];

    protected function casts(): array
    {
        return [
            'environment' => PunchoutEnvironment::class,
            'shared_secret' => 'encrypted',
            'is_active' => 'boolean',
        ];
    }
}
