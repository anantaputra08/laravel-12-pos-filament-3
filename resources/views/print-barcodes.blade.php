<!DOCTYPE html>
<html>
<head>
    <title>Print Barcodes</title>
</head>
<body>
    <table>
        @foreach ($barcodeImages as $barcodeData)
            <tr>
                <td>
                    <h4 style="margin: 0; padding: 0;">{{ $barcodeData['product']->name }}</h4>
                    <img src="data:image/png;base64,{{ $barcodeData['barcodeImage'] }}" alt="Barcode" width="100" height="12" style="display: block; margin: 0; padding: 0;">
                    <p style="margin: 0; padding: 0; font-size: 10px;">{{ $barcodeData['product']->barcode }}</p>
                </td>
            </tr>
        @endforeach
    </table>
</body>
</html>