<?php

use App\Http\Controllers\BarcodeController;
use App\Http\Controllers\PriceTagController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/print-barcode/{barcode}', [BarcodeController::class, 'print'])->name('print.barcode');
Route::get('/print-barcodes', [BarcodeController::class, 'printBulk'])->name('print.barcodes');

Route::get('/print-price-tag/{product_id}', [PriceTagController::class, 'print'])
    ->name('print.priceTag');
