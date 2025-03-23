<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ProductResource\Pages;
use App\Filament\Resources\ProductResource\RelationManagers;
use App\Models\Product;
use App\Models\ProductType;
use App\Models\ProductUnit;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Support\Enums\FontWeight;
use Filament\Tables;
use Filament\Tables\Columns\Layout\Split;
use Filament\Tables\Columns\Layout\Stack;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Support\Collection;
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
                        ->maxLength(255)
                        ->suffixAction(
                            Forms\Components\Actions\Action::make('generateBarcode')
                                ->label('Generate')
                                ->icon('heroicon-o-receipt-refund')
                                ->action(function ($state, $set) {
                                    // Generate a unique barcode
                                    $latestProduct = Product::orderBy('barcode', 'desc')->first();
                                    $latestBarcode = $latestProduct ? intval($latestProduct->barcode) : 0;
                                    $generatedBarcode = str_pad($latestBarcode + 1, 10, '0', STR_PAD_LEFT);

                                    // Ensure the generated barcode is unique
                                    while (Product::where('barcode', $generatedBarcode)->exists()) {
                                        $generatedBarcode = str_pad(intval($generatedBarcode) + 1, 10, '0', STR_PAD_LEFT);
                                    }

                                    $set('barcode', $generatedBarcode);
                                })
                        ),
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
                    Forms\Components\Repeater::make('product_units')
                        ->label('Product Units')
                        ->relationship('productUnits')
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

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('barcode')
                    ->searchable(),
                Tables\Columns\TextColumn::make('name')
                    ->weight(FontWeight::ExtraBold)
                    // ->description(fn(Product $record) => ($record->category?->name ?? '-') . ' | ' . ($record->type?->name ?? '-'))
                    ->searchable(),
                Tables\Columns\TextColumn::make('type.name')
                    ->weight(FontWeight::Thin)
                    ->color('gray')
                    ->sortable(),
                Tables\Columns\TextColumn::make('category.name')
                    ->weight(FontWeight::Thin)
                    ->color('gray')
                    ->sortable(),
                Tables\Columns\TextColumn::make('base_price')
                    ->money('IDR')
                    ->sortable(),
                Tables\Columns\TextColumn::make('selling_price')
                    ->money('IDR')
                    ->sortable(),
                Tables\Columns\TextColumn::make('stock')
                    ->numeric()
                    ->sortable(),
                Tables\Columns\TextColumn::make('base_unit')
                    ->searchable(),
                Tables\Columns\IconColumn::make('is_available')
                    ->alignCenter()
                    ->boolean(),
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
                Tables\Actions\ActionGroup::make([
                    Tables\Actions\ViewAction::make(),
                    Tables\Actions\Action::make('printBarcode')
                        ->label('Print Barcode')
                        ->icon('heroicon-o-printer')
                        ->form([
                            Forms\Components\Select::make('unit')
                                ->label('Select Unit')
                                ->options(function ($record) {
                                    // Fetch product units dynamically
                                    return $record->productUnits->pluck('name', 'id');
                                })
                                ->required(),
                        ])
                        ->action(function ($record, $data) {
                            // Fetch the selected unit
                            $unit = $record->productUnits()->find($data['unit']);

                            // Determine the barcode to use
                            $barcode = $unit && $unit->barcode ? $unit->barcode : $record->barcode;

                            // Redirect to the route that handles the PDF generation
                            return redirect()->route('print.barcode', [
                                'barcode' => $barcode,
                                'unit_id' => $data['unit'],
                            ]);
                        }),
                    Tables\Actions\Action::make('printPriceTag')
                        ->label('Print Price Tag')
                        ->icon('heroicon-o-currency-dollar')
                        ->action(function ($record) {
                            return redirect()->route('print.priceTag', ['product_id' => $record->id]);
                        }),

                    Tables\Actions\EditAction::make(),
                    Tables\Actions\DeleteAction::make(),
                ])->tooltip('Actions'),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                    Tables\Actions\ForceDeleteBulkAction::make(),
                    Tables\Actions\RestoreBulkAction::make(),
                    Tables\Actions\BulkAction::make('printBarcodes')
                        ->label('Print Barcodes')
                        ->icon('heroicon-o-printer')
                        ->action(function (Collection $records) {
                            $barcodes = $records->pluck('barcode')->toArray();
                            // Redirect to the route that handles the bulk PDF generation
                            return redirect()->route('print.barcodes', ['barcodes' => $barcodes]);
                        }),
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