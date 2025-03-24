<?php

namespace App\Filament\Resources;

use App\Filament\Resources\TransactionResource\Pages;
use App\Filament\Resources\TransactionResource\RelationManagers;
use App\Models\Product;
use App\Models\ProductUnit;
use App\Models\Transaction;
use Filament\Forms;
use Filament\Forms\Components\Actions;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Support\Enums\Alignment;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Tables\Actions\Action;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Support\Facades\Auth;

class TransactionResource extends Resource
{
    protected static ?string $model = Transaction::class;

    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';

    public static function getNavigationBadge(): ?string
    {
        return static::getModel()::whereDate('created_at', now()->toDateString())->count();
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Grid::make(4)->schema([
                    Forms\Components\TextInput::make('order_id')
                        ->default(fn() => Transaction::generateOrderId())
                        ->required()
                        ->disabled()
                        ->dehydrated()
                        ->maxLength(255)
                        ->columnSpan(1),

                    Forms\Components\Select::make('user_id')
                        ->label('Cashier')
                        ->default(fn() => Auth::id())
                        ->relationship('user', 'name')
                        ->disabled()
                        ->dehydrated()
                        ->required()
                        ->columnSpan(1),

                    Forms\Components\Select::make('status')
                        ->options([
                            'pending' => 'Pending',
                            'success' => 'Success',
                            'canceled' => 'Canceled',
                        ])
                        ->required()
                        ->default('pending')
                        ->columnSpan(1),

                    Forms\Components\DateTimePicker::make('expiry_time')
                        ->default(fn() => now()->addMinutes(30))
                        ->required()
                        ->columnSpan(1),
                ]),

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

                Forms\Components\Select::make('product_search')
                    ->label('Search Product')
                    ->options(Product::all()->pluck('name', 'id'))
                    ->live()
                    ->searchable()
                    ->placeholder('Search and select a product')
                    ->afterStateUpdated(function ($state, callable $set, callable $get) {
                        if (empty($state)) {
                            return;
                        }

                        static::addProductById($state, $set, $get);

                        // Reset input product_search agar bisa search produk berikutnya
                        $set('product_search', '');

                        // Hitung ulang gross_amount
                        static::recalculateGrossAmount($set, $get);
                    }),

                Forms\Components\Repeater::make('items')
                    ->relationship('items')
                    ->schema([
                        Forms\Components\Select::make('product_id')
                            ->relationship('product', 'name')
                            ->disabled()
                            ->dehydrated()
                            ->required(),

                        Forms\Components\Select::make('product_unit_id')
                            ->label('Unit')
                            ->options(function (callable $get) {
                                $productId = $get('product_id');
                                if (!$productId) {
                                    return [];
                                }

                                // Fetch units from ProductUnit table
                                $productUnits = ProductUnit::where('product_id', $productId)->get();
                                $options = [];
                                foreach ($productUnits as $unit) {
                                    $conversionText = $unit->conversion_rate > 1
                                        ? " ({$unit->conversion_rate} {$unit->name})"
                                        : '';

                                    $options[$unit->id] = ($unit->name ?? 'Unit') .
                                        " - Rp " . number_format($unit->selling_price) . $conversionText;
                                }

                                return $options;
                            })
                            ->afterStateUpdated(function ($state, callable $set, callable $get) {
                                if (!$state) {
                                    return;
                                }

                                $productId = $get('product_id');
                                if (!$productId) {
                                    return;
                                }

                                $unit = ProductUnit::find($state);
                                if ($unit) {
                                    $set('product_price', $unit->selling_price);
                                    $set('total_price', $unit->selling_price * $get('qty'));
                                    $set('is_base_unit', false);
                                }
                            })
                            ->live()
                            ->required()
                            ->dehydrated(function ($state) {
                                // If base, we do not save product_unit_id but still save null for consistency
                                return true;
                            }),

                        Forms\Components\Hidden::make('is_base_unit')
                            ->default(false)
                            ->dehydrated(), // Pastikan is_base_unit terhidrasi

                        Forms\Components\TextInput::make('product_price')
                            ->disabled()
                            ->prefix('Rp.')
                            ->dehydrated(),

                        Forms\Components\TextInput::make('qty')
                            ->numeric()
                            ->default(1)
                            ->live()
                            ->debounce(800)
                            ->afterStateUpdated(function ($state, callable $set, callable $get) {
                                if (empty($state)) {
                                    return;
                                }
                                
                                if (strlen($state) > 1 && $state[0] === '0') {
                                    $decimalValue = '0.' . substr($state, 1);
                                    $set('qty', (float) $decimalValue); // Set nilai qty ke format desimal
                                }

                                // Update total price
                                $price = $get('product_price');
                                $set('total_price', $price * $get('qty')); // Pastikan menggunakan qty yang sudah diperbarui
                            }),

                        Forms\Components\TextInput::make('total_price')
                            ->disabled()
                            ->prefix('Rp.')
                            ->dehydrated(),
                    ])
                    ->mutateRelationshipDataBeforeCreateUsing(function (array $data): array {
                        // Pastikan product_unit_id disimpan dengan benar
                        if ($data['product_unit_id'] === 'base') {
                            $data['product_unit_id'] = null;
                            $data['is_base_unit'] = true;
                        } else {
                            $data['is_base_unit'] = false;
                        }

                        // Reduce stock based on the transaction
                        $productUnit = ProductUnit::find($data['product_unit_id']);
                        if ($productUnit) {
                            $stockReduction = $data['qty'] * $productUnit->conversion_rate; // Calculate stock reduction
                            $productUnit->product->decrement('stock', $stockReduction); // Reduce stock
                        } elseif ($data['is_base_unit']) {
                            $product = Product::find($data['product_id']);
                            if ($product) {
                                $product->decrement('stock', $data['qty']); // Reduce stock for base unit
                            }
                        }

                        return $data;
                    })
                    ->deleteAction(
                        fn(Forms\Components\Actions\Action $action) => $action
                            ->after(fn(callable $set, callable $get) => static::recalculateGrossAmount($set, $get))
                    )
                    ->reorderable(false)
                    ->collapsible(false)
                    ->columnSpanFull()
                    ->columns(5)
                    ->defaultItems(0)
                    ->live(onBlur: true)
                    ->afterStateUpdated(function (callable $set, callable $get) {
                        static::recalculateGrossAmount($set, $get);
                    }),

                Forms\Components\Grid::make(4)->schema([
                    Forms\Components\TextInput::make('gross_amount')
                        ->required()
                        ->prefix('Rp.')
                        ->default(0.00)
                        ->disabled()
                        ->dehydrated()
                        ->columnSpan(1)
                        ->suffixAction(
                            Forms\Components\Actions\Action::make('recalculate')
                                ->label('Recalculate')
                                ->icon('heroicon-o-receipt-refund')
                                ->tooltip('Recalculate gross amount based on items')
                                ->action(function (callable $set, callable $get) {
                                    // Call the recalculateGrossAmount method
                                    static::recalculateGrossAmount($set, $get);
                                })
                        ),

                    Forms\Components\Select::make('payment_type')
                        ->options([
                            'cash' => 'Cash',
                            'bank_transfer' => 'Bank Transfer',
                            'credit_card' => 'Credit Card',
                            'debit_card' => 'Debit Card',
                            'e_wallet' => 'E-Wallet',
                        ])
                        ->required()
                        ->default('cash')
                        ->columnSpan(1),

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
                        })
                        ->columnSpan(1),

                    Forms\Components\TextInput::make('change_amount')
                        ->required()
                        ->prefix('Rp.')
                        ->default(0.00)
                        ->live()
                        ->disabled()
                        ->dehydrated()
                        ->columnSpan(1),
                ]),

                Forms\Components\Fieldset::make('Quick Amounts')
                    ->columnSpanFull()
                    ->schema([
                        Forms\Components\Actions::make([
                            Forms\Components\Actions\Action::make('1000')
                                ->label('Rp. 1,000')
                                ->action(function (callable $set, callable $get) {
                                    $currentPaidAmount = $get('paid_amount');
                                    $newPaidAmount = $currentPaidAmount + 1000;
                                    $set('paid_amount', $newPaidAmount);

                                    // Explicitly recalculate change amount
                                    $grossAmount = $get('gross_amount');
                                    $changeAmount = $newPaidAmount - $grossAmount;
                                    $set('change_amount', $changeAmount);
                                }),
                            Forms\Components\Actions\Action::make('2000')
                                ->label('Rp. 2,000')
                                ->action(function (callable $set, callable $get) {
                                    $currentPaidAmount = $get('paid_amount');
                                    $newPaidAmount = $currentPaidAmount + 2000;
                                    $set('paid_amount', $newPaidAmount);

                                    // Explicitly recalculate change amount
                                    $grossAmount = $get('gross_amount');
                                    $changeAmount = $newPaidAmount - $grossAmount;
                                    $set('change_amount', $changeAmount);
                                }),
                            Forms\Components\Actions\Action::make('5000')
                                ->label('Rp. 5,000')
                                ->action(function (callable $set, callable $get) {
                                    $currentPaidAmount = $get('paid_amount');
                                    $newPaidAmount = $currentPaidAmount + 5000;
                                    $set('paid_amount', $newPaidAmount);

                                    // Explicitly recalculate change amount
                                    $grossAmount = $get('gross_amount');
                                    $changeAmount = $newPaidAmount - $grossAmount;
                                    $set('change_amount', $changeAmount);
                                }),
                            Forms\Components\Actions\Action::make('10000')
                                ->label('Rp. 10,000')
                                ->action(function (callable $set, callable $get) {
                                    $currentPaidAmount = $get('paid_amount');
                                    $newPaidAmount = $currentPaidAmount + 10000;
                                    $set('paid_amount', $newPaidAmount);

                                    // Explicitly recalculate change amount
                                    $grossAmount = $get('gross_amount');
                                    $changeAmount = $newPaidAmount - $grossAmount;
                                    $set('change_amount', $changeAmount);
                                }),
                            Forms\Components\Actions\Action::make('20000')
                                ->label('Rp. 20,000')
                                ->action(function (callable $set, callable $get) {
                                    $currentPaidAmount = $get('paid_amount');
                                    $newPaidAmount = $currentPaidAmount + 20000;
                                    $set('paid_amount', $newPaidAmount);

                                    // Explicitly recalculate change amount
                                    $grossAmount = $get('gross_amount');
                                    $changeAmount = $newPaidAmount - $grossAmount;
                                    $set('change_amount', $changeAmount);
                                }),
                            Forms\Components\Actions\Action::make('50000')
                                ->label('Rp. 50,000')
                                ->action(function (callable $set, callable $get) {
                                    $currentPaidAmount = $get('paid_amount');
                                    $newPaidAmount = $currentPaidAmount + 50000;
                                    $set('paid_amount', $newPaidAmount);

                                    // Explicitly recalculate change amount
                                    $grossAmount = $get('gross_amount');
                                    $changeAmount = $newPaidAmount - $grossAmount;
                                    $set('change_amount', $changeAmount);
                                }),
                            Forms\Components\Actions\Action::make('100000')
                                ->label('Rp. 100,000')
                                ->action(function (callable $set, callable $get) {
                                    $currentPaidAmount = $get('paid_amount');
                                    $newPaidAmount = $currentPaidAmount + 100000;
                                    $set('paid_amount', $newPaidAmount);

                                    // Explicitly recalculate change amount
                                    $grossAmount = $get('gross_amount');
                                    $changeAmount = $newPaidAmount - $grossAmount;
                                    $set('change_amount', $changeAmount);
                                }),
                            Forms\Components\Actions\Action::make('uang_pas')
                                ->label('Uang Pas')
                                ->action(function (callable $set, callable $get) {
                                    $grossAmount = $get('gross_amount');
                                    $set('paid_amount', $grossAmount);
                                    $set('change_amount', 0);
                                }),
                            Forms\Components\Actions\Action::make('reset')
                                ->label('Reset')
                                ->action(function (callable $set) {
                                    $set('paid_amount', 0);
                                    $set('change_amount', 0);
                                }),
                        ])
                            ->columnSpanFull(),
                    ]),
            ]);
    }

    public static function addProductByBarcode($barcode, callable $set, callable $get)
    {
        // Jika barcode kosong, jangan lakukan apa-apa
        if (empty($barcode)) {
            return false;
        }

        // Cari berdasarkan barcode di tabel ProductUnit terlebih dahulu
        // Ini penting karena ProductUnit barcode bisa jadi merupakan subset dari Product barcode
        $productUnit = ProductUnit::where('barcode', $barcode)->first();

        if ($productUnit) {
            // Jika ditemukan di ProductUnit, gunakan unit tersebut
            $product = $productUnit->product;
            $isBaseUnit = false;
            $unitId = $productUnit->id;
            $price = $productUnit->selling_price;
        } else {
            // Jika tidak ditemukan di ProductUnit, cari di Product
            $product = Product::where('barcode', $barcode)->first();

            if (!$product) {
                return false; // Produk tidak ditemukan
            }

            // Cari unit dengan conversion_rate = 1 untuk produk ini
            $baseUnit = ProductUnit::where('product_id', $product->id)
                ->where('conversion_rate', 1)
                ->first();

            if ($baseUnit) {
                $isBaseUnit = false; // Kita gunakan ProductUnit
                $unitId = $baseUnit->id;
                $price = $baseUnit->selling_price;
            } else {
                // Jika tidak ada unit dengan conversion_rate = 1, gunakan harga dari Product
                $isBaseUnit = true;
                $unitId = 'base';
                $price = $product->selling_price;
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
                } elseif (!$isBaseUnit && isset($item['product_unit_id']) && $item['product_unit_id'] == $unitId) {
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
                'product_unit_id' => $unitId,
                'qty' => 1,
                'product_price' => $price,
                'total_price' => $price,
            ];

            $items[] = $newItem;
        }

        $set('items', $items);
        return true; // Produk ditemukan dan ditambahkan
    }

    public static function addProductById($productId, callable $set, callable $get)
    {
        // Jika productId kosong, jangan lakukan apa-apa
        if (empty($productId)) {
            return false;
        }

        // Cari berdasarkan productId di tabel Product
        $product = Product::find($productId);
        if (!$product) {
            return false; // Produk tidak ditemukan
        }

        // Cari unit dengan conversion_rate = 1
        $productUnit = ProductUnit::where('product_id', $productId)
            ->where('conversion_rate', 1)
            ->first();

        $isBaseUnit = true;
        $unitId = 'base';

        if ($productUnit) {
            $isBaseUnit = false;
            $unitId = $productUnit->id;
        }

        // Kita sudah menemukan produk, sekarang tambahkan ke items
        $items = $get('items') ?? [];

        // Cek apakah produk dengan unit yang sama sudah ada di dalam repeater
        $existingItemKey = null;
        foreach ($items as $key => $item) {
            if ($item['product_id'] == $product->id) {
                // Jika produk sudah ada, tambahkan quantity
                $existingItemKey = $key;
                break;
            }
        }

        if ($existingItemKey !== null) {
            // Jika produk dengan unit yang sama sudah ada, tambahkan quantity
            $currentQty = $items[$existingItemKey]['qty'];
            $items[$existingItemKey]['qty'] = $currentQty + 1;
            $items[$existingItemKey]['total_price'] = $items[$existingItemKey]['product_price'] * ($currentQty + 1);
        } else {
            // Jika produk belum ada, tambahkan sebagai item baru
            $newItem = [
                'product_id' => $product->id,
                'is_base_unit' => $isBaseUnit,
                'product_unit_id' => $unitId,
                'qty' => 1,
                'product_price' => $isBaseUnit ? $product->selling_price : $productUnit->selling_price,
                'total_price' => $isBaseUnit ? $product->selling_price : $productUnit->selling_price,
            ];

            $items[] = $newItem;
        }

        $set('items', $items);
        return true; // Produk ditemukan dan ditambahkan
    }

    public static function recalculateGrossAmount(callable $set, callable $get)
    {
        $items = $get('items') ?? [];
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
                Tables\Columns\TextColumn::make('paid_amount')
                    ->money('IDR')
                    ->sortable(),
                Tables\Columns\TextColumn::make('change_amount')
                    ->money('IDR')
                    ->sortable(),
                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->color(fn(string $state): string => match ($state) {
                        'pending' => 'warning',
                        'success' => 'success',
                        'canceled' => 'danger',
                        default => 'gray',
                    }),
                // ->options([
                //     'pending' => 'Pending',
                //     'success' => 'Success',
                //     'canceled' => 'Canceled',
                // ])
                // ->rules(['required'])
                // ->afterStateUpdated(function ($record, $state) {
                //     Notification::make()
                //         ->title('Transaction Status Updated')
                //         ->body("Transaction with order ID '{$record->order_id}' has been updated to '{$state}'")
                //         ->success()
                //         ->send();
                // }),
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
                //
            ])
            ->actions([
                Tables\Actions\Action::make('print')
                    ->label('Print Receipt')
                    ->icon('heroicon-o-printer')
                    ->action(fn(Transaction $record) => redirect()->route('receipts.print', $record)),
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
            ])
            ->orderBy('created_at', 'desc');
    }
}