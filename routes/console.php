<?php

use App\Modules\Punchout\Models\PunchoutLog;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Explicit --model rather than relying on model:prune's default app/Models
// scan: this app's models live under app/Modules/*/Models, not app/Models.
Schedule::command('model:prune', ['--model' => [PunchoutLog::class]])->daily();
