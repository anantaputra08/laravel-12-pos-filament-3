<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Barryvdh\DomPDF\Facade\Pdf;
use App\Models\Product;
use App\Models\ProductUnit;

class PriceTagController extends Controller
{
    public function print($product_id)
    {
        // Ambil produk berdasarkan ID
        $product = Product::findOrFail($product_id);

        // Ambil semua unit terkait, urutkan dari terkecil ke terbesar (berdasarkan conversion_rate)
        $units = $product->productUnits()->orderBy('conversion_rate', 'asc')->get();

        // Jika tidak ada unit, gunakan harga dasar produk
        if ($units->isEmpty()) {
            $units = collect([
                (object)[
                    'name' => $product->base_unit,
                    'barcode' => $product->barcode,
                    'selling_price' => $product->selling_price
                ]
            ]);
        }

        // Generate PDF dengan semua unit
        $pdf = Pdf::loadView('print-price-tag', compact('product', 'units'));
        return $pdf->download('price-tag.pdf');
    }
}
