<?php

namespace App\Filament\Resources\TransactionResource\Pages;

use App\Filament\Resources\TransactionResource;
use Filament\Notifications\Actions\Action; // Perbaikan: Menggunakan Action dari Notifications
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Filament\Support\Enums\Alignment;
use Illuminate\Support\Facades\Log;

class CreateTransaction extends CreateRecord
{
    protected static string $resource = TransactionResource::class;

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }

    public function getFormActionsAlignment(): string|Alignment 
    {
        return Alignment::End; // Align tombol ke kanan
    }

    protected function afterCreate(): void
    {
        // Ambil transaksi yang baru dibuat
        $transaction = $this->record;

        // Buat URL untuk mencetak struk
        $receiptUrl = route('receipts.print', ['transaction' => $transaction->id]);

        // Tampilkan notifikasi dengan tombol "Print Receipt"
        Notification::make()
            ->title('Transaction Created')
            ->body("The transaction has been created successfully. Click the button below to print the receipt.")
            ->success()
            ->actions([
                Action::make('Print Receipt') // Perbaikan: Menggunakan Action dari Notifications
                    ->url($receiptUrl)
                    ->openUrlInNewTab(),
            ])
            ->send();
    }
}
