<?php

use App\Http\Controllers\BarcodeController;
use App\Http\Controllers\PriceTagController;
use App\Http\Controllers\ReceiptController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/print-barcode/{barcode}', [BarcodeController::class, 'print'])->name('print.barcode');
Route::get('/print-barcodes', [BarcodeController::class, 'printBulk'])->name('print.barcodes');

Route::get('/print-price-tag/{product_id}', [PriceTagController::class, 'print'])
    ->name('print.priceTag');

// Route::get('/print-receipt/{transaction}', [ReceiptController::class, 'print'])
//     ->name('print-receipt');

Route::get('/receipts/print/{transaction}', [ReceiptController::class, 'print'])->name('receipts.print');
