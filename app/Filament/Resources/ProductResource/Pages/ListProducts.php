<?php

namespace App\Filament\Resources\ProductResource\Pages;

use App\Filament\Resources\ProductResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;
use Filament\Forms\Components\FileUpload;
use Maatwebsite\Excel\Facades\Excel;
use App\Exports\ProductsExport;
use App\Imports\ProductsImport;
use Illuminate\Http\UploadedFile;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Storage;

class ListProducts extends ListRecords
{
    protected static string $resource = ProductResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
            Actions\Action::make('export')
                ->label('Export Products')
                ->icon('heroicon-c-document-arrow-up')
                ->action(function () {
                    try {
                        return Excel::download(new ProductsExport, 'products.xlsx');
                    } catch (\Exception $e) {
                        Notification::make()
                            ->title('Error')
                            ->body('Failed to export products: ' . $e->getMessage())
                            ->danger()
                            ->send();
                    }
                }),
                Actions\Action::make('import')
                ->label('Import Products')
                ->icon('heroicon-c-document-arrow-down')
                ->form([
                    FileUpload::make('file')
                        ->label('Select File')
                        ->required()
                        ->acceptedFileTypes(['application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'text/csv']),
                ])
                ->action(function (array $data) {
                    try {
                        if (!isset($data['file']) || empty($data['file'])) {
                            throw new \Exception('No file uploaded.');
                        }

                        // Path relatif yang diberikan oleh Filament
                        $filePath = $data['file'];

                        // Pastikan file benar-benar ada
                        if (!Storage::disk('local')->exists($filePath)) {
                            throw new \Exception("File does not exist at {$filePath}");
                        }

                        // Ambil path absolut
                        $absolutePath = Storage::disk('local')->path($filePath);

                        // Jalankan import
                        Excel::import(new ProductsImport, $absolutePath);

                        // Hapus file setelah import selesai
                        Storage::disk('local')->delete($filePath);

                        Notification::make()
                            ->title('Success')
                            ->body('Products imported successfully!')
                            ->success()
                            ->send();
                    } catch (\Exception $e) {
                        Notification::make()
                            ->title('Error')
                            ->body('Failed to import products: ' . $e->getMessage())
                            ->danger()
                            ->send();
                    }
                }),
        ];
    }
}
