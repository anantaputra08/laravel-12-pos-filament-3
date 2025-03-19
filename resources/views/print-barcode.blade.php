<!DOCTYPE html>
<html>
<head>
    <title>Print Barcode</title>
</head>
<body>
    <table>
        <tr>
            @for ($i = 0; $i < 4; $i++) <!-- Adjust the number of barcodes as needed -->
                <td>
                    <h4 style="margin: 0; padding: 0;">{{ $product->name }}</h4>
                    <img src="data:image/png;base64,{{ $barcodeImage }}" alt="Barcode" width="100" height="12" style="display: block; margin: 0; padding: 0;">
                    <p style="margin: 0; padding: 0; font-size: 10px;">{{ $finalBarcode }}</p>
                </td>
            @endfor
        </tr>
    </table>
</body>
</html>