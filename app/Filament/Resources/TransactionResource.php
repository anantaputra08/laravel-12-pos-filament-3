<?php

namespace App\Filament\Resources;

use App\Filament\Resources\TransactionResource\Pages;
use App\Filament\Resources\TransactionResource\RelationManagers;
use App\Models\Product;
use App\Models\ProductUnit;
use App\Models\Transaction;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
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

                Forms\Components\TextInput::make('status')
                    ->required()
                    ->maxLength(255)
                    ->default('pending'),

                Forms\Components\TextInput::make('payment_type')
                    ->maxLength(255)
                    ->default(null),

                Forms\Components\TextInput::make('barcode_input')
                    ->label('Scan Barcode')
                    ->helperText('Auto add product to list')
                    ->afterStateUpdated(function ($state, callable $set, callable $get) {
                        if (empty($state)) {
                            return;
                        }

                        $productFound = static::addProductByBarcode($state, $set, $get);

                        // Jika produk tidak ditemukan, tampilkan notifikasi
                        if (!$productFound) {
                            Notification::make()
                                ->title('Produk tidak ditemukan')
                                ->body("Produk dengan barcode '{$state}' tidak tersedia di database")
                                ->danger()
                                ->send();
                        }

                        // Reset input barcode agar bisa scan produk berikutnya
                        $set('barcode_input', '');

                        // Hitung ulang gross_amount
                        static::recalculateGrossAmount($set, $get);
                    })
                    ->live()
                    ->debounce(1000), // Tambahkan debounce untuk menghindari multiple submit

                Forms\Components\DateTimePicker::make('expiry_time')
                    ->default(fn() => now()->addMinutes(30))
                    ->required(),

                Forms\Components\Repeater::make('items')
                    ->relationship('items')
                    ->schema([
                        Forms\Components\Select::make('product_id')
                            ->relationship('product', 'name')
                            ->disabled()
                            ->dehydrated(),

                        Forms\Components\Select::make('product_unit_id')
                            ->label('Unit')
                            ->options(function (callable $get) {
                                $productId = $get('product_id');
                                if (!$productId) {
                                    return [];
                                }

                                $product = Product::find($productId);
                                if (!$product) {
                                    return [];
                                }

                                // Mulai dengan satuan dasar dari Product
                                $options = [
                                    'base' => 'Pcs - Rp ' . number_format($product->selling_price)
                                ];

                                // Tambahkan unit-unit lain dari ProductUnit
                                $productUnits = ProductUnit::where('product_id', $productId)->get();
                                foreach ($productUnits as $unit) {
                                    $conversionText = $unit->conversion_rate > 1
                                        ? " ({$unit->conversion_rate} {$product->base_unit})"
                                        : '';

                                    $options[$unit->id] = ($unit->name ?? 'Unit') .
                                        " - Rp " . number_format($unit->selling_price) . $conversionText;
                                }

                                return $options;
                            })
                            ->reactive()
                            ->afterStateUpdated(function ($state, callable $set, callable $get) {
                                if (!$state) {
                                    return;
                                }

                                $productId = $get('product_id');
                                if (!$productId) {
                                    return;
                                }

                                if ($state === 'base') {
                                    // Jika menggunakan satuan dasar
                                    $product = Product::find($productId);
                                    if ($product) {
                                        $set('product_price', $product->selling_price);
                                        $set('total_price', $product->selling_price * $get('qty'));
                                        $set('is_base_unit', true);
                                    }
                                } else {
                                    // Jika menggunakan unit lain
                                    $unit = ProductUnit::find($state);
                                    if ($unit) {
                                        $set('product_price', $unit->selling_price);
                                        $set('total_price', $unit->selling_price * $get('qty'));
                                        $set('is_base_unit', false);
                                    }
                                }
                            })
                            ->live() // Pastikan komponen ini live
                            ->required(),

                        Forms\Components\Hidden::make('is_base_unit')
                            ->default(false),

                        Forms\Components\TextInput::make('product_price')
                            ->disabled()
                            ->prefix('Rp.')
                            ->dehydrated(),

                        Forms\Components\TextInput::make('qty')
                            ->numeric()
                            ->default(1)
                            ->live()
                            ->afterStateUpdated(function ($state, callable $set, callable $get) {
                                // Update total price
                                $price = $get('product_price');
                                $set('total_price', $price * $state);
                            }),

                        Forms\Components\TextInput::make('total_price')
                            ->disabled()
                            ->prefix('Rp.')
                            ->dehydrated(),
                    ])
                    ->deleteAction(
                        fn(Forms\Components\Actions\Action $action) => $action
                            ->after(fn(callable $set, callable $get) => static::recalculateGrossAmount($set, $get))
                    )
                    ->reorderable(false)
                    ->collapsible(false)
                    ->columnSpanFull()
                    ->columns(5)
                    ->defaultItems(0)
                    // Gunakan live dengan modifikasi untuk memicu recalculate setiap kali repeater berubah
                    ->live(onBlur: true)
                    ->afterStateUpdated(function (callable $set, callable $get) {
                        static::recalculateGrossAmount($set, $get);
                    }),

                Forms\Components\TextInput::make('gross_amount')
                    ->required()
                    ->prefix('Rp.')
                    ->default(0.00)
                    ->disabled()
                    ->dehydrated(),

                Forms\Components\TextInput::make('paid_amount')
                    ->required()
                    ->prefix('Rp.')
                    ->default(0.00)
                    ->live()
                    ->debounce(700)
                    ->afterStateUpdated(function ($state, callable $set, callable $get) {
                        $grossAmount = $get('gross_amount');
                        $changeAmount = $state - $grossAmount;
                        $set('change_amount', $changeAmount);
                    }),

                Forms\Components\TextInput::make('change_amount')
                    ->required()
                    ->prefix('Rp.')
                    ->default(0.00)
                    ->disabled()
                    ->dehydrated(),
            ]);
    }

    public static function addProductByBarcode($barcode, callable $set, callable $get)
    {
        // Jika barcode kosong, jangan lakukan apa-apa
        if (empty($barcode)) {
            return false;
        }

        // Cari berdasarkan barcode di tabel Product
        $product = Product::where('barcode', $barcode)->first();
        $productUnit = null;
        $isBaseUnit = true;

        // Jika tidak ditemukan di Product, cari di ProductUnit
        if (!$product) {
            $productUnit = ProductUnit::where('barcode', $barcode)->first();

            if ($productUnit) {
                $product = $productUnit->product;
                $isBaseUnit = false;
            } else {
                return false; // Produk tidak ditemukan
            }
        }

        // Kita sudah menemukan produk, sekarang tambahkan ke items
        $items = $get('items') ?? [];

        // Cek apakah produk dengan unit yang sama sudah ada di dalam repeater
        $existingItemKey = null;
        foreach ($items as $key => $item) {
            if ($item['product_id'] == $product->id) {
                // Cek apakah unit sama (baik base unit maupun product unit)
                $isSameUnit = false;

                if ($isBaseUnit && isset($item['is_base_unit']) && $item['is_base_unit']) {
                    $isSameUnit = true;
                } elseif (!$isBaseUnit && isset($item['product_unit_id']) && $item['product_unit_id'] == $productUnit->id) {
                    $isSameUnit = true;
                }

                if ($isSameUnit) {
                    $existingItemKey = $key;
                    break;
                }
            }
        }

        if ($existingItemKey !== null) {
            // Jika produk dengan unit yang sama sudah ada, tambahkan quantity
            $currentQty = $items[$existingItemKey]['qty'];
            $items[$existingItemKey]['qty'] = $currentQty + 1;
            $items[$existingItemKey]['total_price'] = $items[$existingItemKey]['product_price'] * ($currentQty + 1);
        } else {
            // Jika produk belum ada atau unit berbeda, tambahkan sebagai item baru
            $newItem = [
                'product_id' => $product->id,
                'is_base_unit' => $isBaseUnit,
                'qty' => 1,
            ];

            if ($isBaseUnit) {
                $newItem['product_unit_id'] = 'base';
                $newItem['product_price'] = $product->selling_price;
                $newItem['total_price'] = $product->selling_price;
            } else {
                $newItem['product_unit_id'] = $productUnit->id;
                $newItem['product_price'] = $productUnit->selling_price;
                $newItem['total_price'] = $productUnit->selling_price;
            }

            $items[] = $newItem;
        }

        $set('items', $items);
        return true; // Produk ditemukan dan ditambahkan
    }

    public static function recalculateGrossAmount(callable $set, callable $get)
    {
        $items = $get('items') ?? [];
        // Hapus dd($items) yang menghentikan eksekusi function

        $grossAmount = 0;

        foreach ($items as $item) {
            // Menggunakan total_price yang sudah ada dan memastikan itu adalah angka
            if (isset($item['total_price']) && is_numeric($item['total_price'])) {
                $grossAmount += (float) $item['total_price'];
            }
            // Alternatif jika total_price tidak valid, hitung dari product_price dan qty
            elseif (
                isset($item['product_price']) && isset($item['qty']) &&
                is_numeric($item['product_price']) && is_numeric($item['qty'])
            ) {
                $grossAmount += (float) $item['product_price'] * (float) $item['qty'];
            }
        }

        // Set gross_amount ke nilai yang baru dihitung
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
                    ->color(fn(string $state): string => match ($state) {
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