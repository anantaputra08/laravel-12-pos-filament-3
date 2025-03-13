<?php

use App\Http\Controllers\BarcodeController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/print-barcode/{barcode}', [BarcodeController::class, 'print'])->name('print.barcode');
