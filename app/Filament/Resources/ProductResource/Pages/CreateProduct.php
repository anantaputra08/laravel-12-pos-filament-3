<?php

namespace App\Filament\Resources\ProductResource\Pages;

use App\Filament\Resources\ProductResource;
use Filament\Actions;
use Filament\Notifications\Notification;
use Illuminate\Database\QueryException;
use Filament\Resources\Pages\CreateRecord;

class CreateProduct extends CreateRecord
{
    protected static string $resource = ProductResource::class;
    protected function handleRecordCreation(array $data): \Illuminate\Database\Eloquent\Model
    {
        try {
            return parent::handleRecordCreation($data);
        } catch (QueryException $exception) {
            if ($exception->errorInfo[1] === 1062) {
                Notification::make()
                    ->title('Gagal menyimpan produk!')
                    ->body('Barcode sudah terdaftar. Silakan gunakan barcode lain.')
                    ->danger()
                    ->send();
            } else {
                Notification::make()
                    ->title('Terjadi kesalahan!')
                    ->body($exception->getMessage())
                    ->danger()
                    ->send();
            }

            // Supaya Filament tidak lanjut ke redirect setelah error
            $this->halt();
            throw $exception;
        }
    }
}
