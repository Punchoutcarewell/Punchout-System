<?php

declare(strict_types=1);

namespace App\Modules\Admin\Filament\Resources\ProductResource\Pages;

use App\Modules\Admin\Filament\Resources\ProductResource;
use App\Modules\Catalog\Data\CatalogImportReport;
use App\Modules\Catalog\Data\ProductImageBulkUploadReport;
use App\Modules\Catalog\Services\CatalogExporter;
use App\Modules\Catalog\Services\CatalogImporter;
use App\Modules\Catalog\Services\ProductImageBulkUploader;
use App\Shared\Exceptions\DomainValidationException;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\CreateAction;
use Filament\Forms\Components\FileUpload;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

final class ListProducts extends ListRecords
{
    protected static string $resource = ProductResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('import')
                ->label('Import')
                ->icon('heroicon-o-arrow-up-tray')
                ->color('gray')
                ->form([
                    FileUpload::make('file')
                        ->label('Catalogue file')
                        ->required()
                        ->disk('local')
                        ->directory('catalog-imports')
                        ->visibility('private')
                        ->acceptedFileTypes([
                            'text/csv',
                            'text/plain',
                            'application/vnd.ms-excel',
                            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                        ])
                        ->helperText('.csv, .xlsx, or .xls, same column layout either way.'),
                ])
                ->action(function (array $data): void {
                    $relativePath = $data['file'];

                    try {
                        $report = app(CatalogImporter::class)->importFromFile(
                            Storage::disk('local')->path($relativePath),
                        );

                        $this->notifyImportReport($report);
                    } catch (DomainValidationException $exception) {
                        Notification::make()
                            ->title('Import failed')
                            ->body($exception->getMessage())
                            ->danger()
                            ->send();
                    } finally {
                        Storage::disk('local')->delete($relativePath);
                    }
                }),

            Action::make('bulkUploadImages')
                ->label('Upload images')
                ->icon('heroicon-o-photo')
                ->color('gray')
                ->form([
                    FileUpload::make('images')
                        ->label('Image files')
                        ->required()
                        ->multiple()
                        ->image()
                        ->preserveFilenames()
                        ->disk('public')
                        ->directory('products')
                        ->visibility('public')
                        ->helperText('Matched to products by filename: a product whose image_path column (from Import) is "CW-9013.jpg" gets whichever uploaded file is named exactly that.'),
                ])
                ->action(function (array $data): void {
                    $report = app(ProductImageBulkUploader::class)->applyUploadedImages($data['images']);

                    $this->notifyBulkImageUploadReport($report);
                }),

            ActionGroup::make([
                Action::make('exportCsv')
                    ->label('CSV')
                    ->icon('heroicon-o-document-text')
                    ->action(fn (): BinaryFileResponse => $this->downloadAndForget(app(CatalogExporter::class)->exportToCsvFile())),
                Action::make('exportExcel')
                    ->label('Excel (.xlsx)')
                    ->icon('heroicon-o-table-cells')
                    ->action(fn (): BinaryFileResponse => $this->downloadAndForget(app(CatalogExporter::class)->exportToExcelFile())),
            ])
                ->label('Export')
                ->icon('heroicon-o-arrow-down-tray')
                ->color('gray')
                ->button(),

            CreateAction::make(),
        ];
    }

    private function notifyImportReport(CatalogImportReport $report): void
    {
        $title = "Imported: {$report->created} created, {$report->updated} updated";

        if (! $report->hasIssues()) {
            Notification::make()->title($title)->success()->send();

            return;
        }

        $preview = collect($report->issues)
            ->take(5)
            ->map(fn ($issue): string => "Row {$issue->rowNumber}: {$issue->reason}")
            ->implode("\n");

        $remaining = count($report->issues) - 5;

        if ($remaining > 0) {
            $preview .= "\n… and {$remaining} more.";
        }

        Notification::make()
            ->title($title)
            ->body(count($report->issues)." row(s) skipped:\n{$preview}")
            ->warning()
            ->persistent()
            ->send();
    }

    private function notifyBulkImageUploadReport(ProductImageBulkUploadReport $report): void
    {
        $title = "Uploaded: {$report->matched} matched to a product";

        if (! $report->hasUnmatched()) {
            Notification::make()->title($title)->success()->send();

            return;
        }

        $preview = collect($report->unmatchedFilenames)->take(5)->implode("\n");
        $remaining = count($report->unmatchedFilenames) - 5;

        if ($remaining > 0) {
            $preview .= "\n… and {$remaining} more.";
        }

        Notification::make()
            ->title($title)
            ->body(count($report->unmatchedFilenames)." file(s) had no matching product's image_path:\n{$preview}")
            ->warning()
            ->persistent()
            ->send();
    }

    private function downloadAndForget(string $path): BinaryFileResponse
    {
        return response()->download($path)->deleteFileAfterSend();
    }
}
