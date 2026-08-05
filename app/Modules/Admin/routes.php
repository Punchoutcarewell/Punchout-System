<?php

declare(strict_types=1);

use App\Modules\Admin\Http\Controllers\PunchoutPreviewController;
use Filament\Http\Middleware\Authenticate;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Admin module routes
|--------------------------------------------------------------------------
|
| Filament itself registers /admin/* (login, resources, pages) directly
| from the panel() definition in AdminPanelProvider; this file is only
| for the small number of plain, non-Filament routes the Admin module
| needs alongside it.
|
*/

Route::middleware(['web', Authenticate::class])
    ->post('/admin/punchout-preview/complete', [PunchoutPreviewController::class, 'complete'])
    ->name('admin.punchout-preview.complete');
