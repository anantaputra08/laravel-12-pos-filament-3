<?php

namespace App\Filament\Resources\TransactionResource\Pages;

use App\Filament\Resources\TransactionResource;
use Filament\Actions;
use Filament\Resources\Pages\CreateRecord;
use Filament\Support\Enums\Alignment;

class CreateTransaction extends CreateRecord
{
    protected static string $resource = TransactionResource::class;
    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
    public function getFormActionsAlignment(): string|Alignment 
    {
        return Alignment::End; // This will align buttons to the left
    }
}
