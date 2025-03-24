<?php

namespace App\Http\Controllers;

use App\Models\Transaction;
use Illuminate\Http\Request;

class ReceiptController extends Controller
{
    public function print(Transaction $transaction)
    {
        // Muat relasi yang diperlukan
        $transaction->load('items.product', 'user');
        
        // Render view cetak nota
        return view('receipts.print', [
            'transaction' => $transaction,
        ]);
    }
}