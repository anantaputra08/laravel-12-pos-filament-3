<?php
namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Barryvdh\DomPDF\Facade\Pdf;
use Milon\Barcode\DNS1D;
use App\Models\Product;

class BarcodeController extends Controller
{
    public function print($barcode)
    {
        $product = Product::where('barcode', $barcode)->firstOrFail();
        $barcodeGenerator = new DNS1D();
        $barcodeImage = $barcodeGenerator->getBarcodePNG($barcode, 'C39');
        $pdf = Pdf::loadView('print-barcode', compact('product', 'barcode', 'barcodeImage'));
        return $pdf->download('barcode.pdf');
    }
}