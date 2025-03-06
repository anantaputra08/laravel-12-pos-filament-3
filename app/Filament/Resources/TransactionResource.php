<?php

namespace App\Filament\Resources;

use App\Filament\Resources\TransactionResource\Pages;
use App\Filament\Resources\TransactionResource\RelationManagers;
use App\Models\Product;
use App\Models\Transaction;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Support\Facades\Auth;

class TransactionResource extends Resource
{
    protected static ?string $model = Transaction::class;

    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('order_id')
                    ->default(fn() => Transaction::generateOrderId())
                    ->required()
                    ->disabled()
                    ->dehydrated()
                    ->maxLength(255),

                Forms\Components\Select::make('user_id')
                    ->label('Cashier')
                    ->default(fn() => Auth::id())
                    ->relationship('user', 'name')
                    ->disabled()
                    ->dehydrated()
                    ->required(),

                Forms\Components\TextInput::make('gross_amount')
                    ->required()
                    ->numeric()
                    ->default(0.00),

                Forms\Components\TextInput::make('status')
                    ->required()
                    ->maxLength(255)
                    ->default('pending'),

                Forms\Components\TextInput::make('payment_type')
                    ->maxLength(255)
                    ->default(null),

                Forms\Components\DateTimePicker::make('expiry_time')
                    ->default(fn() => now()->addMinutes(30))
                    ->required(),

                // 🔍 Input Barcode - Ditingkatkan
                Forms\Components\TextInput::make('barcode_input')
                    ->label('Scan Barcode')
                    ->helperText('Masukkan barcode produk dan tekan Enter')
                    ->afterStateUpdated(function ($state, callable $set, callable $get) {
                        if (empty($state)) {
                            return;
                        }
                        
                        static::addProductByBarcode($state, $set, $get);
                        
                        // Reset input barcode agar bisa scan produk berikutnya
                        $set('barcode_input', '');
                        
                        // Hitung ulang gross_amount
                        static::recalculateGrossAmount($set, $get);
                    })
                    ->live()
                    ->debounce(500), // Tambahkan debounce untuk menghindari multiple submit

                // 📝 Repeater untuk menampilkan daftar produk
                Forms\Components\Repeater::make('items')
                    ->relationship('items')
                    ->schema([
                        Forms\Components\Select::make('product_id')
                            ->relationship('product', 'name')
                            ->disabled()
                            ->dehydrated(),
                            
                        Forms\Components\TextInput::make('product_price')
                            ->disabled()
                            ->numeric()
                            ->dehydrated(),
                            
                        Forms\Components\TextInput::make('qty')
                            ->numeric()
                            ->default(1)
                            ->live()
                            ->afterStateUpdated(function ($state, $set, callable $get, $record) {
                                // Update total price
                                $price = $get('product_price');
                                $set('total_price', $price * $state);
                                
                                // Recalculate gross amount
                                static::recalculateGrossAmount($set, $get);
                            }),
                            
                        Forms\Components\TextInput::make('total_price')
                            ->disabled()
                            ->numeric()
                            ->dehydrated(),
                    ])
                    ->deleteAction(
                        fn (Forms\Components\Actions\Action $action) => $action
                            ->after(fn (callable $set, callable $get) => static::recalculateGrossAmount($set, $get))
                    )
                    ->reorderable(false)
                    ->collapsible(false)
                    ->defaultItems(0),
            ]);
    }

    public static function addProductByBarcode($barcode, callable $set, callable $get)
    {
        // Jika barcode kosong, jangan lakukan apa-apa
        if (empty($barcode)) {
            return;
        }
        
        $product = Product::where('barcode', $barcode)->first();

        if ($product) {
            $items = $get('items') ?? [];
            
            // Cek apakah produk sudah ada di dalam repeater
            $existingItemKey = null;
            foreach ($items as $key => $item) {
                if ($item['product_id'] == $product->id) {
                    $existingItemKey = $key;
                    break;
                }
            }
            
            if ($existingItemKey !== null) {
                // Jika produk sudah ada, tambahkan quantity
                $currentQty = $items[$existingItemKey]['qty'];
                $items[$existingItemKey]['qty'] = $currentQty + 1;
                $items[$existingItemKey]['total_price'] = $product->selling_price * ($currentQty + 1);
            } else {
                // Jika produk belum ada, tambahkan sebagai item baru
                $items[] = [
                    'product_id' => $product->id,
                    'product_price' => $product->selling_price,
                    'qty' => 1,
                    'total_price' => $product->selling_price,
                ];
            }
            
            $set('items', $items);
        }
    }
    
    public static function recalculateGrossAmount(callable $set, callable $get)
    {
        $items = $get('items') ?? [];
        $grossAmount = 0;
        
        foreach ($items as $item) {
            $grossAmount += $item['total_price'];
        }
        
        $set('gross_amount', $grossAmount);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('order_id')
                    ->searchable(),
                Tables\Columns\TextColumn::make('user.name')
                    ->label('Cashier')
                    ->sortable(),
                Tables\Columns\TextColumn::make('gross_amount')
                    ->money('IDR')
                    ->sortable(),
                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'pending' => 'warning',
                        'success' => 'success',
                        'canceled' => 'danger',
                        default => 'gray',
                    })
                    ->searchable(),
                Tables\Columns\TextColumn::make('payment_type')
                    ->searchable(),
                Tables\Columns\TextColumn::make('expiry_time')
                    ->dateTime()
                    ->sortable(),
                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\TrashedFilter::make(),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
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
            'index' => Pages\ListTransactions::route('/'),
            'create' => Pages\CreateTransaction::route('/create'),
            'edit' => Pages\EditTransaction::route('/{record}/edit'),
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