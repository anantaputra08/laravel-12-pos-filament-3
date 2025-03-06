<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ProductResource\Pages;
use App\Filament\Resources\ProductResource\RelationManagers;
use App\Models\Product;
use App\Models\ProductUnit;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Support\Facades\Log;

class ProductResource extends Resource
{
    protected static ?string $model = Product::class;

    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Product Details')->schema([
                    Forms\Components\TextInput::make('barcode')
                        ->required()
                        ->maxLength(255),
                    Forms\Components\TextInput::make('name')
                        ->required()
                        ->maxLength(255),
                    Forms\Components\Select::make('type_id')
                        ->label('Product Type')
                        ->required()
                        ->relationship('type', 'name')
                        ->default('1')
                        ->searchable()
                        ->preload()
                        ->reactive(),

                    // Repeater for ProductUnit fields
                    Forms\Components\Repeater::make('product_units') // UBAH NAMA DARI productUnits KE product_units
                        ->label('Product Units')
                        ->relationship('productUnits') // Tambahkan relasi
                        ->schema([
                            Forms\Components\TextInput::make('barcode')
                                ->label('Product Unit Barcode')
                                ->maxLength(255),
                            Forms\Components\TextInput::make('name')
                                ->label('Product Unit Name')
                                ->required()
                                ->maxLength(255),
                            Forms\Components\TextInput::make('selling_price')
                                ->label('Unit Price')
                                ->prefix('Rp')
                                ->required()
                                ->numeric(),
                            Forms\Components\TextInput::make('conversion_rate')
                                ->label('Amount PCS')
                                ->required()
                                ->numeric(),
                        ])
                        ->visible(fn($get) => $get('type_id') == '2')
                        ->columns(4)
                        ->createItemButtonLabel('Add Product Unit')
                        ->deletable()
                        ->addable()
                        ->reorderable(),

                    Forms\Components\Select::make('category_id')
                        ->label('Product Category')
                        ->required()
                        ->relationship('category', 'name')
                        ->searchable()
                        ->preload(),
                    Forms\Components\Toggle::make('is_available')
                        ->default(true)
                        ->required(),
                    Forms\Components\Toggle::make('is_stock')
                        ->default(true)
                        ->required()
                        ->reactive(),
                    Forms\Components\TextInput::make('base_price')
                        ->prefix('Rp')
                        ->required()
                        ->numeric(),
                    Forms\Components\TextInput::make('selling_price')
                        ->prefix('Rp')
                        ->required()
                        ->numeric(),
                    Forms\Components\TextInput::make('stock')
                        ->required()
                        ->numeric()
                        ->disabled(function ($get) {
                            return !$get('is_stock');
                        }),
                    Forms\Components\TextInput::make('min_stock')
                        ->required()
                        ->numeric()
                        ->default(0),
                    Forms\Components\TextInput::make('weight')
                        ->prefix('gr')
                        ->numeric(),
                    Forms\Components\TextInput::make('base_unit')
                        ->placeholder('Pcs/Lusin/Gram')
                        ->required()
                        ->maxLength(255),
                ])
            ]);
    }

    public static function create(array $data): Product
    {
        // Tambahkan debug log
        Log::info('Creating Product', $data);

        // Cek apakah product units ada
        if (isset($data['productUnits'])) {
            Log::info('Product Units', $data['productUnits']);
        }

        // Create the product
        $product = Product::create($data);

        // Simpan ProductUnits jika ada
        if (isset($data['productUnits'])) {
            foreach ($data['productUnits'] as $productUnitData) {
                // Log setiap product unit yang akan disimpan
                Log::info('Saving Product Unit', $productUnitData);

                // Set the product_id untuk setiap ProductUnit
                $productUnit = $product->productUnits()->create(array_merge($productUnitData, ['product_id' => $product->id]));

                // Tambahkan log untuk memastikan pembuatan berhasil
                Log::info('Product Unit Created', ['id' => $productUnit->id]);
            }
        }

        return $product;
    }

    public static function update(array $data, Product $record): Product
    {
        // Tambahkan debug log
        Log::info('Updating Product', $data);

        // Update the product
        $record->update($data);

        // Update ProductUnits
        if (isset($data['productUnits'])) {
            // Log units yang akan disimpan
            Log::info('Product Units to Update', $data['productUnits']);

            // Hapus unit lama
            $record->productUnits()->delete();

            // Buat unit baru
            foreach ($data['productUnits'] as $productUnitData) {
                // Log setiap unit yang akan dibuat
                Log::info('Creating Product Unit', $productUnitData);

                $productUnit = $record->productUnits()->create(array_merge($productUnitData, ['product_id' => $record->id]));

                // Log konfirmasi pembuatan
                Log::info('Product Unit Created', ['id' => $productUnit->id]);
            }
        }

        return $record;
    }
    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('barcode')
                    ->searchable(),
                Tables\Columns\TextColumn::make('name')
                    ->searchable(),
                Tables\Columns\TextColumn::make('type.name')
                    ->numeric()
                    ->sortable(),
                Tables\Columns\TextColumn::make('category.name')
                    ->numeric()
                    ->sortable(),
                Tables\Columns\IconColumn::make('is_available')
                    ->boolean(),
                Tables\Columns\TextColumn::make('base_price')
                    ->numeric()
                    ->sortable(),
                Tables\Columns\TextColumn::make('selling_price')
                    ->numeric()
                    ->sortable(),
                Tables\Columns\TextColumn::make('stock')
                    ->numeric()
                    ->sortable(),
                Tables\Columns\TextColumn::make('base_unit')
                    ->searchable(),
                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('deleted_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\TrashedFilter::make(),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                    Tables\Actions\ForceDeleteBulkAction::make(),
                    Tables\Actions\RestoreBulkAction::make(),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListProducts::route('/'),
            'create' => Pages\CreateProduct::route('/create'),
            'edit' => Pages\EditProduct::route('/{record}/edit'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->withoutGlobalScopes([
                SoftDeletingScope::class,
            ]);
    }
}
