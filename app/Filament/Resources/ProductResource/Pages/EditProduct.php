<?php

namespace App\Filament\Resources\ProductResource\Pages;

use App\Filament\Resources\ProductResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Support\Facades\Log;

class EditProduct extends EditRecord
{
    protected static string $resource = ProductResource::class;

    protected function afterSave(): void
    {
        // Get the product
        $product = $this->record;
        
        // Check if a default product unit already exists (name = base_unit and conversion_rate = 1)
        $hasDefaultUnit = $product->productUnits()
            ->where('name', $product->base_unit)
            ->where('conversion_rate', 1)
            ->exists();
            
        // If no default unit exists, create one
        if (!$hasDefaultUnit) {
            $defaultUnit = [
                'barcode' => null,
                'name' => $product->base_unit,
                'selling_price' => $product->selling_price,
                'conversion_rate' => 1,
                'product_id' => $product->id
            ];
            
            Log::info('Creating Default Product Unit on Update', $defaultUnit);
            $productUnit = $product->productUnits()->create($defaultUnit);
            Log::info('Default Product Unit Created on Update', ['id' => $productUnit->id]);
        }
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
            Actions\ForceDeleteAction::make(),
            Actions\RestoreAction::make(),
        ];
    }
}
