<?php
namespace App\Http\Controllers;

use App\Models\ProductUnit;
use Illuminate\Http\Request;
use Barryvdh\DomPDF\Facade\Pdf;
use Milon\Barcode\DNS1D;
use App\Models\Product;

class BarcodeController extends Controller
{
    public function print($barcode, $unit_id = null)
    {
        // Try to find the product by barcode first
        $product = Product::where('barcode', $barcode)->first();

        if (!$product) {
            // If no product is found, try to find the barcode in ProductUnit
            $unit = ProductUnit::where('barcode', $barcode)->firstOrFail();
            $product = $unit->product; // Get the associated product
            $finalBarcode = $unit->barcode;
        } else {
            // If product exists, check if a unit is selected
            $unit = $unit_id ? ProductUnit::find($unit_id) : null;
            $finalBarcode = $unit && $unit->barcode ? $unit->barcode : $product->barcode;
        }

        // Generate barcode image
        $barcodeGenerator = new DNS1D();
        $barcodeImage = $barcodeGenerator->getBarcodePNG($finalBarcode, 'C39');

        // Generate PDF
        $pdf = Pdf::loadView('print-barcode', compact('product', 'unit', 'finalBarcode', 'barcodeImage'));
        return $pdf->download('barcode.pdf');
    }
    public function printBulk(Request $request)
    {
        $items = $request->input('items', []);
        $barcodeGenerator = new DNS1D();
        $barcodeImages = [];

        foreach ($items as $item) {
            $product = Product::where('barcode', $item['barcode'])->first();
            $unit = null;

            if (!$product) {
                $unit = ProductUnit::where('barcode', $item['barcode'])->first();
                if ($unit) {
                    $product = $unit->product;
                }
            } else {
                $unit = isset($item['unit_id']) ? ProductUnit::find($item['unit_id']) : null;
            }

            if ($product) {
                $finalBarcode = $unit && $unit->barcode ? $unit->barcode : $product->barcode;
                $barcodeImages[] = [
                    'product' => $product,
                    'unit' => $unit,
                    'finalBarcode' => $finalBarcode,
                    'barcodeImage' => $barcodeGenerator->getBarcodePNG($finalBarcode, 'C39'),
                ];
            }
        }

        $pdf = Pdf::loadView('print-barcodes', compact('barcodeImages'));
        return $pdf->download('barcodes.pdf');
    }
}