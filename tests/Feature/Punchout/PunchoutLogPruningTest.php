<?php

declare(strict_types=1);

use App\Modules\Punchout\Enums\PunchoutMessageDirection;
use App\Modules\Punchout\Enums\PunchoutMessageType;
use App\Modules\Punchout\Models\PunchoutLog;
use Illuminate\Console\Scheduling\Schedule;

function createTestPunchoutLog(array $attributes = []): PunchoutLog
{
    return PunchoutLog::query()->create(array_merge([
        'direction' => PunchoutMessageDirection::Inbound,
        'message_type' => PunchoutMessageType::SetupRequest,
        'http_status' => 200,
        'raw_payload' => '<cXML/>',
        'created_at' => now(),
    ], $attributes));
}

it('treats a log row older than the configured retention window as prunable', function () {
    config(['punchout.log_retention_days' => 90]);

    $old = createTestPunchoutLog(['created_at' => now()->subDays(91)]);
    $recent = createTestPunchoutLog(['created_at' => now()->subDays(1)]);

    $prunableIds = (new PunchoutLog)->prunable()->pluck('id');

    expect($prunableIds)->toContain($old->id)
        ->and($prunableIds)->not->toContain($recent->id);
});

it('respects a different retention window from config', function () {
    config(['punchout.log_retention_days' => 7]);

    $withinShortWindow = createTestPunchoutLog(['created_at' => now()->subDays(3)]);
    $beyondShortWindow = createTestPunchoutLog(['created_at' => now()->subDays(10)]);

    $prunableIds = (new PunchoutLog)->prunable()->pluck('id');

    expect($prunableIds)->not->toContain($withinShortWindow->id)
        ->and($prunableIds)->toContain($beyondShortWindow->id);
});

it('schedules model:prune for PunchoutLog to run daily', function () {
    $events = app(Schedule::class)->events();

    $pruneEvent = collect($events)->first(fn ($event) => str_contains($event->command ?? '', 'model:prune'));

    expect($pruneEvent)->not->toBeNull()
        ->and($pruneEvent->command)->toContain(PunchoutLog::class)
        ->and($pruneEvent->expression)->toBe('0 0 * * *');
});
